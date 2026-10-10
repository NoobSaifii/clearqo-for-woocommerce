# Contributing

Thanks for helping improve **Clearqo for WooCommerce**! By taking part you agree to our [Code of Conduct](CODE_OF_CONDUCT.md).

## Reporting bugs

Open an [issue](https://github.com/NoobSaifii/clearqo-for-woocommerce/issues/new/choose) using the bug template. Please include your WordPress, WooCommerce and PHP versions, and the order notes Clearqo added to the affected order.

Never paste API keys, webhook secrets or customer data in an issue. For security problems, follow [SECURITY.md](SECURITY.md).

## Pull requests

1. Fork the repository and create a branch from `main`.
2. Follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/):
   - prefix every function, class, hook and option with `clqwc` / `Clqwc`;
   - escape output late and sanitize input early;
   - use the `clearqo-for-woocommerce` text domain for every string.
3. Keep `assets/blocks.js` plain ES5 with no build step.
4. If your change is user-facing, update `readme.txt` (Changelog) and `CHANGELOG.md`.
5. Make sure CI is green. It runs PHP syntax checks (PHP 7.4 → 8.5), JavaScript and version checks, and the WordPress Plugin Check.

## Releasing (maintainers)

1. Bump the version in the plugin header, in `CLQWC_VERSION` and in `readme.txt` (`Stable tag`), and add a Changelog entry.
2. Tag `vX.Y.Z` and publish a GitHub release with the zip built by:

   ```bash
   git archive --format=zip --prefix=clearqo-for-woocommerce/ -o clearqo-for-woocommerce.zip vX.Y.Z
   ```
