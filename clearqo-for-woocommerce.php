<?php
/**
 * Plugin Name:          Clearqo for WooCommerce
 * Plugin URI:           https://clearqo.com/woocommerce/
 * Description:          Accept USDT in WooCommerce with Binance Pay, TRC20 and BEP20. Customers pay straight into your own Binance account and every payment is verified automatically. Works with stores in any currency and with TeraWallet top-ups.
 * Version:              1.2.0
 * Author:               Clearqo
 * Author URI:           https://clearqo.com/
 * License:              GPLv2 or later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          clearqo-for-woocommerce
 * Requires at least:    6.5
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 8.0
 * WC tested up to:      11.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CLQWC_ID', 'clearqo' );
define( 'CLQWC_VERSION', '1.2.0' );

/* ---------- settings / API client ---------- */

function clqwc_settings() {
	$s = get_option( 'woocommerce_' . CLQWC_ID . '_settings', array() );
	return is_array( $s ) ? $s : array();
}

// wp-config constants win over the settings screen (keeps secrets out of the DB if you prefer).
function clqwc_conf( $key ) {
	$const = array( 'api_base' => 'CLEARQO_API_BASE', 'api_key' => 'CLEARQO_API_KEY', 'webhook_secret' => 'CLEARQO_WEBHOOK_SECRET' );
	if ( isset( $const[ $key ] ) && defined( $const[ $key ] ) ) {
		return trim( (string) constant( $const[ $key ] ) );
	}
	$s = clqwc_settings();
	return isset( $s[ $key ] ) ? trim( (string) $s[ $key ] ) : '';
}

function clqwc_api( $method, $path, $body = null ) {
	$base = untrailingslashit( clqwc_conf( 'api_base' ) );
	if ( 0 !== strpos( $base, 'https://' ) || '' === clqwc_conf( 'api_key' ) ) {
		return new WP_Error( 'clqwc_conf', __( 'Clearqo is not configured.', 'clearqo-for-woocommerce' ) );
	}
	$res = wp_remote_request( $base . '/wp-json/clearqo/v1' . $path, array(
		'method'      => $method,
		'timeout'     => 20,
		'redirection' => 0,
		'headers'     => array( 'X-Api-Key' => clqwc_conf( 'api_key' ), 'Content-Type' => 'application/json', 'Accept' => 'application/json' ),
		'body'        => null === $body ? null : wp_json_encode( $body ),
	) );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code >= 300 || ! is_array( $data ) ) {
		/* translators: %d: HTTP status code */
		$fallback = sprintf( __( 'Payment service error (HTTP %d).', 'clearqo-for-woocommerce' ), $code );
		return new WP_Error( 'clqwc_api', is_array( $data ) && ! empty( $data['message'] ) ? sanitize_text_field( $data['message'] ) : $fallback );
	}
	return $data;
}

/* ---------- helpers ---------- */

function clqwc_is_topup( $order ) {
	if ( ! function_exists( 'get_wallet_rechargeable_product' ) || ! get_wallet_rechargeable_product() ) {
		return false;
	}
	$rid   = (int) get_wallet_rechargeable_product()->get_id();
	$items = $order->get_items();
	foreach ( $items as $item ) {
		if ( (int) $item->get_product_id() !== $rid ) {
			return false;
		}
	}
	return ! empty( $items );
}

/**
 * One confirmation at a time per order: the webhook, the thank-you page and cron can arrive together,
 * and a payment (or a TeraWallet credit) must never be applied twice. Returns false if the lock timed out.
 */
function clqwc_apply( $order, $inv ) {
	global $wpdb;
	$lock = $order ? 'clqwc_' . $wpdb->prefix . $order->get_id() : '';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- MySQL advisory lock, not a data query; must never be cached.
	if ( ! $lock || ! $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 15)', $lock ) ) ) {
		return false;
	}
	clqwc_apply_now( wc_get_order( $order->get_id() ), $inv ); // fresh copy: another request may have just paid it
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- releases the advisory lock above.
	$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	return true;
}

/**
 * Applies the AUTHORITATIVE invoice (fetched from the API, never from a webhook body) to the order.
 * Idempotent: safe to call many times.
 */
function clqwc_apply_now( $order, $inv ) {
	if ( ! $order || ! is_array( $inv ) || empty( $inv['id'] ) || $inv['id'] !== $order->get_meta( '_clqwc_invoice' ) || (string) $inv['order_id'] !== (string) $order->get_id() ) {
		return;
	}
	$status = isset( $inv['status'] ) ? $inv['status'] : '';
	if ( 'paid' === $status && ! $order->is_paid() ) {
		$cur  = strtoupper( $order->get_currency() );
		$pcur = strtoupper( isset( $inv['price_currency'] ) ? (string) $inv['price_currency'] : 'USDT' );
		$usd  = array( 'USD', 'USDT' );
		if ( $pcur !== $cur && ! ( in_array( $cur, $usd, true ) && in_array( $pcur, $usd, true ) ) ) {
			/* translators: 1: invoice currency, 2: order currency */
			$order->update_status( 'on-hold', sprintf( __( 'Clearqo: invoice currency %1$s does not match the order currency %2$s. Check before fulfilling.', 'clearqo-for-woocommerce' ), $pcur, $cur ) );
			return;
		}
		// Amount received, expressed in the order's currency at the rate locked on the invoice.
		$paid = isset( $inv['paid_price_amount'] ) ? (string) $inv['paid_price_amount'] : (string) ( isset( $inv['paid_amount'] ) ? $inv['paid_amount'] : '' );
		$usdt = isset( $inv['paid_amount'] ) ? (string) $inv['paid_amount'] : '';
		if ( ! preg_match( '/^\d{1,15}(\.\d{1,8})?$/', $paid ) || (float) $paid <= 0 ) {
			return;
		}
		$label = $usdt . ' USDT' . ( in_array( $cur, $usd, true ) ? '' : ' (= ' . $paid . ' ' . $cur . ')' );
		$order->update_meta_data( '_clqwc_paid_amount', $paid );
		$order->update_meta_data( '_clqwc_paid_usdt', sanitize_text_field( $usdt ) );
		$order->update_meta_data( '_clqwc_txid', sanitize_text_field( (string) $inv['txid'] ) );
		if ( clqwc_is_topup( $order ) ) {
			// The wallet gets EXACTLY what the customer paid, in the store currency.
			foreach ( $order->get_items() as $item ) {
				$item->set_subtotal( (float) $paid );
				$item->set_total( (float) $paid );
				$item->save();
				break;
			}
			$order->calculate_totals( false );
		} elseif ( (float) $paid + 0.000001 < (float) $order->get_total( 'edit' ) ) {
			/* translators: %s: amount received */
			$order->update_status( 'on-hold', sprintf( __( 'Clearqo: received %s, less than the order total. Check before fulfilling.', 'clearqo-for-woocommerce' ), $label ) );
			$order->save();
			return;
		}
		$order->update_meta_data( '_clqwc_verified', 'yes' );
		/* translators: 1: amount received, 2: payment method, 3: transaction ID */
		$order->add_order_note( sprintf( __( 'Clearqo payment confirmed: %1$s via %2$s — TX %3$s', 'clearqo-for-woocommerce' ), $label, strtoupper( (string) $inv['paid_method'] ), sanitize_text_field( (string) $inv['txid'] ) ) );
		$order->save();
		$order->payment_complete( substr( sanitize_text_field( (string) $inv['txid'] ), 0, 100 ) );
		if ( clqwc_is_topup( $order ) ) {
			$order = wc_get_order( $order->get_id() );
			if ( $order && ! $order->has_status( 'completed' ) ) {
				$order->update_status( 'completed', __( 'Wallet top-up completed.', 'clearqo-for-woocommerce' ) );
			}
		}
	} elseif ( 'review' === $status && $order->has_status( 'pending' ) ) {
		$order->update_status( 'on-hold', __( 'Clearqo: a payment arrived that needs review — approve or reject it in your Clearqo dashboard.', 'clearqo-for-woocommerce' ) );
	} elseif ( 'rejected' === $status && ! $order->is_paid() && ! $order->has_status( 'cancelled' ) ) {
		$order->update_status( 'cancelled', __( 'Clearqo: payment rejected.', 'clearqo-for-woocommerce' ) );
	}
}

function clqwc_refresh( $order ) {
	$id = $order ? (string) $order->get_meta( '_clqwc_invoice' ) : '';
	if ( ! preg_match( '/^inv_[a-f0-9]{24}$/', $id ) || $order->is_paid() || get_transient( 'clqwc_chk_' . $order->get_id() ) ) {
		return;
	}
	set_transient( 'clqwc_chk_' . $order->get_id(), 1, 15 );
	$inv = clqwc_api( 'GET', '/invoices/' . $id );
	if ( ! is_wp_error( $inv ) ) {
		clqwc_apply( $order, $inv );
	}
}

/* ---------- WooCommerce compatibility: HPOS + Cart/Checkout blocks ---------- */

add_action( 'before_woocommerce_init', function () {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

/* ---------- gateway ---------- */

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	class Clqwc_Gateway extends WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = CLQWC_ID;
			$this->has_fields         = false;
			$this->method_title       = __( 'Clearqo (USDT)', 'clearqo-for-woocommerce' );
			$this->method_description = __( 'Binance Pay, USDT TRC20 and USDT BEP20 — paid directly into your Binance, verified by Clearqo.', 'clearqo-for-woocommerce' )
				. ' <a href="https://clearqo.com/merchant/?view=register" target="_blank" rel="noopener">' . __( 'Create a free Clearqo account', 'clearqo-for-woocommerce' ) . '</a>';
			$this->order_button_text  = __( 'Pay with USDT', 'clearqo-for-woocommerce' );
			$this->supports           = array( 'products' );
			$this->init_form_fields();
			$this->init_settings();
			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled     = $this->get_option( 'enabled' );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'        => array( 'title' => __( 'Enable', 'clearqo-for-woocommerce' ), 'type' => 'checkbox', 'label' => __( 'Enable Clearqo', 'clearqo-for-woocommerce' ), 'default' => 'no' ),
				'title'          => array( 'title' => __( 'Title', 'clearqo-for-woocommerce' ), 'type' => 'text', 'default' => __( 'USDT — Binance Pay / TRC20 / BEP20', 'clearqo-for-woocommerce' ) ),
				'description'    => array( 'title' => __( 'Description', 'clearqo-for-woocommerce' ), 'type' => 'textarea', 'default' => __( 'Pay with Binance Pay (no fee), USDT TRC20 or USDT BEP20.', 'clearqo-for-woocommerce' ) ),
				'api_base'       => array( 'title' => __( 'Platform URL', 'clearqo-for-woocommerce' ), 'type' => 'text', 'description' => __( 'Copy it from your Clearqo dashboard → Developers (https only).', 'clearqo-for-woocommerce' ), 'default' => 'https://clearqo.com' ),
				'api_key'        => array( 'title' => __( 'API key', 'clearqo-for-woocommerce' ), 'type' => 'password', 'description' => __( 'Clearqo dashboard → Developers → Create API key. Or define CLEARQO_API_KEY in wp-config.php.', 'clearqo-for-woocommerce' ), 'default' => '' ),
				/* translators: %s: webhook URL of this store */
				'webhook_secret' => array( 'title' => __( 'Webhook signing secret', 'clearqo-for-woocommerce' ), 'type' => 'password', 'description' => sprintf( __( 'Set the webhook endpoint in your Clearqo dashboard to: %s', 'clearqo-for-woocommerce' ), rest_url( 'clearqo-wc/v1/webhook' ) ), 'default' => '' ),
			);
		}

		public function process_admin_options() {
			$saved = parent::process_admin_options();
			$base  = $this->get_option( 'api_base' );
			if ( '' !== $base && 0 !== strpos( $base, 'https://' ) ) {
				WC_Admin_Settings::add_error( __( 'Clearqo: Platform URL must start with https://', 'clearqo-for-woocommerce' ) );
			}
			$me = clqwc_api( 'GET', '/me' );
			if ( is_wp_error( $me ) ) {
				/* translators: %s: error message */
				WC_Admin_Settings::add_error( sprintf( __( 'Clearqo connection test failed: %s', 'clearqo-for-woocommerce' ), $me->get_error_message() ) );
			} else {
				/* translators: 1: business name, 2: plan, 3: Binance connection status, 4: balance in USDT */
				WC_Admin_Settings::add_message( sprintf( __( 'Clearqo connected: %1$s · plan %2$s · Binance %3$s · balance %4$s USDT', 'clearqo-for-woocommerce' ), $me['business_name'] ?? '', $me['plan'] ?? '', $me['binance'] ?? '', $me['balance'] ?? '' ) );
				$cur = strtoupper( get_woocommerce_currency() );
				if ( empty( $me['currencies'][ $cur ] ) ) {
					/* translators: %s: store currency code */
					WC_Admin_Settings::add_error( sprintf( __( 'Clearqo: your store currency is %s but no exchange rate is set for it. Add it in your Clearqo dashboard → Settings → Currencies, otherwise checkout will refuse USDT payments.', 'clearqo-for-woocommerce' ), $cur ) );
				}
			}
			return $saved;
		}

		public function is_available() {
			return 'yes' === $this->enabled && '' !== clqwc_conf( 'api_key' ) && 0 === strpos( clqwc_conf( 'api_base' ), 'https://' ) && parent::is_available();
		}

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return array( 'result' => 'failure' );
			}
			$inv = clqwc_api( 'POST', '/invoices', array(
				'amount'         => wc_format_decimal( $order->get_total( 'edit' ), min( 8, max( 2, (int) wc_get_price_decimals() ) ) ),
				'currency'       => $order->get_currency(),
				'order_id'       => (string) $order->get_id(),
				'description'    => 'Order #' . $order->get_order_number() . ' — ' . get_bloginfo( 'name' ),
				'customer_email' => $order->get_billing_email(),
				'success_url'    => $this->get_return_url( $order ),
				'cancel_url'     => wc_get_checkout_url(),
				'webhook_url'    => rest_url( 'clearqo-wc/v1/webhook' ),
			) );
			// The payment page must be on the configured platform, never wherever a response points.
			if ( is_wp_error( $inv ) || 0 !== strpos( (string) ( $inv['checkout_url'] ?? '' ), untrailingslashit( clqwc_conf( 'api_base' ) ) . '/' ) || ! preg_match( '/^inv_[a-f0-9]{24}$/', (string) ( $inv['id'] ?? '' ) ) ) {
				// Customers get a neutral message; the real reason goes into the order notes for the store owner.
				/* translators: %s: error message */
				$order->add_order_note( sprintf( __( 'Clearqo invoice could not be created: %s', 'clearqo-for-woocommerce' ), is_wp_error( $inv ) ? $inv->get_error_message() : __( 'unexpected response', 'clearqo-for-woocommerce' ) ) );
				wc_add_notice( __( 'USDT payment is temporarily unavailable. Please try again later or contact the store.', 'clearqo-for-woocommerce' ), 'error' );
				return array( 'result' => 'failure' );
			}
			$order->update_meta_data( '_clqwc_invoice', $inv['id'] );
			$order->update_meta_data( '_clqwc_amount_to_pay', sanitize_text_field( (string) $inv['amount_to_pay'] ) );
			$order->update_meta_data( '_clqwc_checkout_url', esc_url_raw( $inv['checkout_url'] ) );
			$order->save();
			/* translators: 1: invoice ID, 2: exact USDT amount */
			$order->update_status( 'pending', sprintf( __( 'Clearqo invoice %1$s — customer must send exactly %2$s USDT.', 'clearqo-for-woocommerce' ), $inv['id'], sanitize_text_field( (string) $inv['amount_to_pay'] ) ) );
			return array( 'result' => 'success', 'redirect' => esc_url_raw( $inv['checkout_url'] ) );
		}
	}

	add_filter( 'woocommerce_payment_gateways', function ( $g ) {
		$g[] = 'Clqwc_Gateway';
		return $g;
	} );
}, 20 );

// Checkout block support (the default checkout on new WooCommerce stores).
add_action( 'woocommerce_blocks_loaded', function () {
	if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		return;
	}

	final class Clqwc_Blocks extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {
		protected $name = CLQWC_ID;

		public function initialize() {
			$this->settings = clqwc_settings();
		}

		public function is_active() {
			return 'yes' === $this->get_setting( 'enabled' );
		}

		public function get_payment_method_script_handles() {
			wp_register_script( 'clqwc-blocks', plugins_url( 'assets/blocks.js', __FILE__ ), array( 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ), CLQWC_VERSION, true );
			return array( 'clqwc-blocks' );
		}

		public function get_payment_method_data() {
			return array(
				'title'       => $this->get_setting( 'title' ),
				'description' => $this->get_setting( 'description' ),
				'supports'    => array( 'products' ),
			);
		}
	}

	add_action( 'woocommerce_blocks_payment_method_type_registration', function ( $registry ) {
		$registry->register( new Clqwc_Blocks() );
	} );
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . CLQWC_ID ) ) . '">' . esc_html__( 'Settings', 'clearqo-for-woocommerce' ) . '</a>' );
	return $links;
} );

/* ---------- webhook ---------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'clearqo-wc/v1', '/webhook', array(
		'methods'             => 'POST',
		'callback'            => 'clqwc_webhook',
		'permission_callback' => '__return_true', // authenticated by HMAC signature below
	) );
} );

function clqwc_webhook( WP_REST_Request $req ) {
	$secret = clqwc_conf( 'webhook_secret' );
	$body   = $req->get_body();
	parse_str( str_replace( ',', '&', (string) $req->get_header( 'x-clearqo-signature' ) ), $sig );
	$t = isset( $sig['t'] ) ? (int) $sig['t'] : 0;
	$v = isset( $sig['v1'] ) ? (string) $sig['v1'] : '';
	if ( '' === $secret || '' === $v || abs( time() - $t ) > 300 || ! hash_equals( hash_hmac( 'sha256', $t . '.' . $body, $secret ), $v ) ) {
		return new WP_Error( 'clqwc_sig', 'Invalid signature.', array( 'status' => 400 ) );
	}
	$event = json_decode( $body, true );
	$data  = is_array( $event ) && isset( $event['data'] ) && is_array( $event['data'] ) ? $event['data'] : array();
	if ( ! empty( $data['order_id'] ) && ! empty( $data['id'] ) ) {
		$order = wc_get_order( absint( $data['order_id'] ) );
		if ( $order && CLQWC_ID === $order->get_payment_method() && $data['id'] === $order->get_meta( '_clqwc_invoice' ) ) {
			// Never trust the webhook body for money: re-read the invoice from the API.
			$inv = clqwc_api( 'GET', '/invoices/' . $order->get_meta( '_clqwc_invoice' ) );
			if ( is_wp_error( $inv ) ) {
				return new WP_Error( 'clqwc_retry', 'Temporary error, retry later.', array( 'status' => 503 ) );
			}
			if ( ! clqwc_apply( $order, $inv ) ) {
				return new WP_Error( 'clqwc_retry', 'Busy, retry later.', array( 'status' => 503 ) );
			}
		}
	}
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/* ---------- fallbacks if a webhook is missed ---------- */

add_action( 'woocommerce_before_thankyou', function ( $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || CLQWC_ID !== $order->get_payment_method() ) {
		return;
	}
	clqwc_refresh( $order );
	$order = wc_get_order( $order_id );
	if ( ! $order->is_paid() && $order->has_status( 'pending' ) && $order->get_meta( '_clqwc_invoice' ) ) {
		echo '<div class="woocommerce-info">' . sprintf(
			/* translators: 1: USDT amount, 2: opening link tag, 3: closing link tag */
			esc_html__( 'Waiting for your USDT payment of %1$s USDT. %2$sOpen the payment page%3$s and refresh this page after paying.', 'clearqo-for-woocommerce' ),
			'<b>' . esc_html( $order->get_meta( '_clqwc_amount_to_pay' ) ) . '</b>',
			'<a href="' . esc_url( (string) $order->get_meta( '_clqwc_checkout_url' ) ) . '">',
			'</a>'
		) . '</div>';
	}
}, 1 );

add_action( 'clqwc_cron', function () {
	$orders = wc_get_orders( array(
		'payment_method' => CLQWC_ID,
		'status'         => array( 'pending', 'cancelled' ),
		'date_created'   => '>' . ( time() - 2 * DAY_IN_SECONDS ),
		'limit'          => 25,
	) );
	foreach ( $orders as $order ) {
		clqwc_refresh( $order );
	}
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'clqwc_cron' ) ) {
		wp_schedule_event( time() + 300, 'hourly', 'clqwc_cron' );
	}
} );
register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'clqwc_cron' );
} );

/* ---------- TeraWallet safety net ---------- */

// A Clearqo top-up credits the wallet ONLY after the API confirmed it, and never more than was paid.
add_filter( 'woo_wallet_credit_purchase_amount', function ( $amount, $order_id ) {
	$order = wc_get_order( $order_id );
	if ( ! $order || CLQWC_ID !== $order->get_payment_method() ) {
		return $amount;
	}
	if ( 'yes' !== $order->get_meta( '_clqwc_verified' ) ) {
		$order->add_order_note( __( 'Wallet credit blocked: Clearqo payment not verified.', 'clearqo-for-woocommerce' ) );
		return 0;
	}
	return min( (float) $amount, (float) $order->get_meta( '_clqwc_paid_amount' ) ); // both in the store currency
}, 1000, 2 );

add_action( 'admin_notices', function () {
	if ( current_user_can( 'manage_woocommerce' ) && function_exists( 'get_wallet_rechargeable_product' ) && in_array( strtoupper( get_woocommerce_currency() ), array( 'USD', 'USDT' ), true ) && (int) wc_get_price_decimals() < 4 ) {
		echo '<div class="notice notice-warning"><p>' . sprintf(
			/* translators: %d: current number of price decimals */
			esc_html__( 'Clearqo: WooCommerce → Settings → General → Number of decimals is %d. Set it to 4 or more so TeraWallet credits amounts like 10.0023 in full.', 'clearqo-for-woocommerce' ),
			(int) wc_get_price_decimals()
		) . '</p></div>';
	}
} );
