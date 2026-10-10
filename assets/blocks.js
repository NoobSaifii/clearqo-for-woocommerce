/* Clearqo payment method for the WooCommerce Cart & Checkout blocks. Plain ES5, no build step. */
( function () {
	'use strict';
	var registry = window.wc && window.wc.wcBlocksRegistry;
	var settings = window.wc && window.wc.wcSettings ? window.wc.wcSettings.getSetting( 'clearqo_data', {} ) : {};
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
