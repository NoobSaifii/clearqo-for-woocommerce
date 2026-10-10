# Changelog

All notable changes to **Clearqo for WooCommerce** are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project uses [Semantic Versioning](https://semver.org/).

## [1.2.0] - 2026-10-10

### Added
- Support for the WooCommerce **Cart & Checkout blocks**.
- Declared compatibility with **High-Performance Order Storage (HPOS)**.
- Translation-ready: every string uses the `clearqo-for-woocommerce` text domain.
- A **Settings** link on the Plugins screen.

### Security
- Each order is locked while a payment confirmation is applied, so a payment (or a TeraWallet credit) can never be applied twice. A busy lock answers the webhook with `503` so Clearqo retries.
- The hosted payment page URL must be on the configured Clearqo platform URL.

## 1.1.0 - 2026-10-10

### Added
- Stores in **any currency** (EUR, PKR, INR…) using the merchant's own exchange rate, locked per order.
- Orders are put on hold when the invoice currency doesn't match the order currency.

### Changed
- Customers see a neutral message when USDT payments are unavailable, and the exact reason is added to the order notes.

## 1.0.0 - 2026-10-10

### Added
- First release: USDT payments with Binance Pay, TRC20 and BEP20 through Clearqo.
- Signed webhooks with server-side re-verification of every invoice.
- TeraWallet top-ups credited with exactly the amount received.

[1.2.0]: https://github.com/NoobSaifii/clearqo-for-woocommerce/releases/tag/v1.2.0
