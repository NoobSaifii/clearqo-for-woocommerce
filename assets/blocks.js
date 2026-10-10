/* Clearqo payment method for the WooCommerce Cart & Checkout blocks. Plain ES5, no build step. */
( function () {
	'use strict';
	var registry = window.wc && window.wc.wcBlocksRegistry;
	var wcSettings = window.wc && window.wc.wcSettings;
	// Newer WooCommerce: getPaymentMethodData(); older versions: the clearqo_data setting.
	var settings = ! wcSettings ? {} : ( wcSettings.getPaymentMethodData ? wcSettings.getPaymentMethodData( 'clearqo', {} ) : wcSettings.getSetting( 'clearqo_data', {} ) ) || {};
	if ( ! registry || ! window.wp || ! window.wp.element ) {
		return;
	}
	var el = window.wp.element.createElement;
	var decode = window.wp.htmlEntities ? window.wp.htmlEntities.decodeEntities : function ( s ) { return s; };
	var title = decode( settings.title || 'USDT — Binance Pay / TRC20 / BEP20' );
	var Content = function () {
		return el( 'div', null, decode( settings.description || '' ) );
	};

	registry.registerPaymentMethod( {
		name: 'clearqo',
		label: el( 'span', null, title ),
		ariaLabel: title,
		content: el( Content, null ),
		edit: el( Content, null ),
		canMakePayment: function () { return true; },
		supports: { features: settings.supports || [ 'products' ] }
	} );
} )();
