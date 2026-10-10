=== Clearqo for WooCommerce ===
Contributors: clearqo
Tags: usdt, binance pay, crypto payments, payment gateway, cryptocurrency
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept USDT in WooCommerce with Binance Pay, TRC20 and BEP20. Paid straight to your own Binance, verified automatically. Non-custodial.

== Description ==

**Clearqo for WooCommerce** is a non-custodial **USDT payment gateway** for WooCommerce. Your customers pay with **Binance Pay**, **USDT TRC20** or **USDT BEP20**, the money goes **directly into your own Binance account**, and Clearqo confirms each payment automatically and completes the order.

No middleman holds your funds, there are no payout delays, and you never have to check transaction hashes by hand again.

= Why store owners choose Clearqo =

* **Paid directly to your Binance** – Clearqo never holds, forwards or touches your money.
* **Automatic verification** – every payment is matched against your Binance history using a read-only API key, and the order is marked as paid within about a minute.
* **Unique amounts, zero confusion** – each order gets a unique amount (for example 25.0037 USDT), so every payment is matched to exactly one order.
* **Binance Pay with no network fee** – customers who use Binance pay in seconds; others can send USDT on TRON (TRC20) or BNB Smart Chain (BEP20).
* **Any store currency** – sell in USD, EUR, PKR, INR, NGN or any other currency. You set your own exchange rate and the order total is converted to USDT at checkout.
* **TeraWallet top-ups** – customers can top up their TeraWallet balance with USDT and the wallet is credited with exactly what they paid.
* **Works with the Checkout block** – supports both the block-based checkout and the classic shortcode checkout, plus High-Performance Order Storage (HPOS).
* **Hosted payment page** – a fast, mobile-friendly page with copy buttons, a countdown and step-by-step instructions.

= How it works =

1. Create a free account at [clearqo.com](https://clearqo.com/) and connect a **read-only** Binance API key.
2. Install this plugin and paste your API key.
3. At checkout the customer chooses **USDT** and sees the exact amount to send.
4. The customer pays with Binance Pay, TRC20 or BEP20.
5. Clearqo finds the payment in your Binance history and marks the order as paid. Your store is notified instantly through a signed webhook.

= Built for security =

* Clearqo only accepts **read-only** Binance API keys. Keys that can withdraw, trade or transfer are refused.
* Webhooks are signed with HMAC-SHA256 and the plugin always re-checks the payment with the Clearqo API before changing an order.
* Each order is locked while it is being confirmed, so a payment can never be applied — or a wallet credited — twice.
* If the amount received is less than the order total, or the currency doesn't match, the order is put on hold instead of being completed.

= Supported payment methods =

* Binance Pay (Binance ID / Pay ID)
* USDT on TRON – TRC20
* USDT on BNB Smart Chain – BEP20

= Requirements =

* WooCommerce 8.0 or newer
* A Clearqo account (free to create; usage is billed per payment or with a monthly plan from a prepaid balance – see [pricing](https://clearqo.com/#pricing))
* A Binance account

= External services =

This plugin connects to the **Clearqo API** (https://clearqo.com/), a payment verification service operated by Clearqo. It is required: the plugin cannot verify payments without it.

* **When:** when a customer places an order with the USDT payment method; when the thank-you page or the hourly background check looks up an unpaid order; when you save the plugin settings (connection test).
* **What is sent:** the order total and currency, the order ID and order number, your store name, the customer's billing email, the return URLs of your store, and your Clearqo API key (to authenticate). No card data or passwords are ever sent.
* **What is received:** the invoice (amount to pay, status, transaction ID). Clearqo also sends signed webhooks to `/wp-json/clearqo-wc/v1/webhook` on your store.

Clearqo [Terms of Service](https://clearqo.com/terms/) · [Privacy Policy](https://clearqo.com/privacy/)

Clearqo is an independent service and is not affiliated with, endorsed by or sponsored by Binance. Binance and Binance Pay are trademarks of their respective owners.

= Development =

Development happens on [GitHub](https://github.com/NoobSaifii/clearqo-for-woocommerce). Bug reports and pull requests are welcome; please report security issues privately through [GitHub](https://github.com/NoobSaifii/clearqo-for-woocommerce/security/advisories/new) or to support@clearqo.com.

== Installation ==

1. In WordPress go to **Plugins → Add New**, search for **Clearqo**, then click **Install Now** and **Activate**.
2. Sign up at [clearqo.com](https://clearqo.com/merchant/?view=register) and connect your read-only Binance API key in **Dashboard → Binance**.
3. In the Clearqo dashboard open **Developers**, create an API key and copy your webhook signing secret.
4. In WordPress open **WooCommerce → Settings → Payments → Clearqo (USDT)**, paste the API key and the signing secret, and enable the method.
5. In the Clearqo dashboard set the webhook endpoint to `https://your-store.com/wp-json/clearqo-wc/v1/webhook`.
6. Store not in USD? Add your exchange rate in **Clearqo dashboard → Settings → Currencies**.

== Frequently Asked Questions ==

= Does Clearqo hold my money? =

No. Customers pay straight into your own Binance account. Clearqo only reads your payment history through a read-only API key, so it cannot move your funds.

= Which payment methods can customers use? =

Binance Pay, USDT TRC20 (TRON) and USDT BEP20 (BNB Smart Chain).

= My store is not in USD. Can I still accept USDT? =

Yes. Set your own exchange rate (for example 1 USDT = 280 PKR) in the Clearqo dashboard. The order total is converted to USDT at checkout and the rate is locked for that order.

= What happens if a customer sends the wrong amount? =

The payment is flagged for review in your Clearqo dashboard and the order is put on hold. You can approve or reject it with one click.

= Does it work with TeraWallet? =

Yes. Wallet top-ups paid in USDT are credited with exactly the amount received. For USD stores, set WooCommerce → Settings → General → Number of decimals to 4 so amounts like 10.0023 are credited in full.

= Does it support the WooCommerce Checkout block? =

Yes. It works with the block-based Cart & Checkout and the classic shortcode checkout, and it is compatible with High-Performance Order Storage (HPOS).

= How much does it cost? =

Creating an account is free. You pay a small fee per confirmed payment or choose a monthly plan, both from a prepaid balance. See [clearqo.com/#pricing](https://clearqo.com/#pricing).

= Can I use Clearqo without WooCommerce? =

Yes. Clearqo has a REST API and signed webhooks for any website, app or bot. See the [API documentation](https://clearqo.com/developers/).

== Screenshots ==

1. Hosted payment page: the exact USDT amount, Binance Pay ID or wallet address, copy buttons and a live countdown.
2. Clearqo dashboard: every payment with its status, method and transaction ID.
3. Developers page: plugin download, API keys and signed webhooks.
4. Your own exchange rates for stores priced in PKR, EUR, INR or any other currency.

== Changelog ==

= 1.2.0 =
* New: support for the WooCommerce Cart & Checkout blocks.
* New: declared compatibility with High-Performance Order Storage (HPOS).
* New: translation-ready.
* New: "Settings" link on the Plugins screen.
* Security: each order is locked while a payment is confirmed, so it can never be applied twice.
* Security: the payment page must be on the configured Clearqo platform URL.

= 1.1.0 =
* New: stores in any currency, using your own exchange rate.
* Improved: clearer messages when USDT payments are unavailable.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.2.0 =
Adds Checkout block support and HPOS compatibility, plus extra protection against duplicate confirmations.
