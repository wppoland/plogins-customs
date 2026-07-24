=== Customs - EU Import Duty for WooCommerce ===
Contributors: motylanogha
Tags: woocommerce, import duty, customs, eu, checkout
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Estimate and add the EU flat import duty as a clear checkout line for parcels shipped into the EU from outside it. WooCommerce ready.

== Description ==

From 1 July 2026 the EU removes the 150 EUR duty-free threshold for low-value imports and applies a flat customs duty per tariff line on consignments up to 150 EUR shipped into the EU from outside it. Customs estimates that duty and shows it to the shopper as its own line at cart and checkout, so there are no surprise charges on delivery.

It only adds the duty when all of these are true: the feature is enabled, the store ships from outside the EU, the destination is an EU country, and the cart goods value is at or below your threshold. Intra-EU orders and orders over the threshold are left untouched.

What it does:

* Adds an "EU import duty (estimate)" fee at cart and checkout using the native WooCommerce fees API
* Calculates the duty as the number of distinct tariff lines in the cart multiplied by your per-line amount
* Counts tariff lines from a per-product tariff code, falling back to the product category, then the product
* Works in the classic checkout and the Cart and Checkout Blocks, and is HPOS compatible
* Per-line amount, threshold, store origin country, EUR conversion rate, tariff-line basis, fee label and taxable flag are all configurable
* Adds taxes on top: the duty is shown as its own line in addition to VAT

This is the WooCommerce equivalent of the import duty handling that hosted platforms add at checkout, without a monthly subscription.

= Documentation and links =

* **Documentation** - [plogins.com/plogins-customs/docs/](https://plogins.com/plogins-customs/docs/)
* **Plugin page** - [plogins.com/plogins-customs/](https://plogins.com/plogins-customs/)
* **Source code** - [github.com/wppoland/plogins-customs](https://github.com/wppoland/plogins-customs)
* **Bug reports and feature requests** - [GitHub issues](https://github.com/wppoland/plogins-customs/issues)

== Translations ==

Customs includes Polish, German and Spanish translations for the plugin interface. The text domain is `plogins-customs`, so WordPress.org language packs can also override or extend these bundled translations.

== Installation ==

1. Install and activate WooCommerce.
2. Install Customs and activate it.
3. Open WooCommerce and then EU Import Duty, set your per-line amount and threshold, and confirm your store origin country.
4. Assign tariff lines to products if you want finer control, otherwise each distinct product category counts as one line.

== Frequently Asked Questions ==

= When does the duty apply? =
Only for orders shipping to an EU country from a store based outside the EU, with a goods value at or below your threshold (150 EUR by default). Intra-EU orders are excluded.

= How is the duty calculated? =
The number of distinct tariff lines in the cart multiplied by your per-line amount (3 EUR by default). A parcel of one product type is one line; a parcel spanning several distinct categories counts as several lines.

= Does it work with the Cart and Checkout Blocks? =
Yes. The duty is added through the native WooCommerce fees API, so it appears in both the classic checkout and the Blocks checkout, and it is HPOS compatible.

= Is the amount exact? =
It is an estimate based on your settings. Final duties and any national handling fees are determined by customs at import. Keep your per-line amount and threshold up to date with current rules.

= Can I sell in a currency other than EUR? =
Yes. Set the EUR to store-currency rate in the settings and the duty is converted before it is added.


= Does this plugin work on WordPress Multisite? =

Yes. This plugin is compatible with WordPress Multisite. Network activate it or activate it on individual sites; each site keeps its own settings and data.

== Screenshots ==

1. The estimated EU import duty shown as its own line in the cart totals.
2. The EU Import Duty settings under WooCommerce: per-line amount, threshold, origin country and how tariff lines are counted.
3. The same duty line in the cart on mobile.

== Changelog ==

= 1.0.6 =
* Declared compatibility with WooCommerce 10.9.

= 1.0.5 =
* Documentation: readme links are now labelled links.

= 1.0.4 =
* Shortened display name (dropped the Plogins prefix; slug unchanged).

= 1.0.3 =
* Added bundled Polish, German and Spanish translations for the plugin interface.

= 1.0.2 =
* Corrected the Polish, German and Spanish translations (customs terminology: Einfuhrzoll, Zolltarifnummer, cło importowe, arancel).

= 1.0.1 =
* First stable release.

= 0.1.4 =
* Added bundled Polish, German and Spanish translations for the plugin interface.
* Refreshed the translation template for the current text domain and settings strings.

= 0.1.3 =
* Added a settings-screen overview of upcoming PRO features (live exchange rates, HS-code classification). No change to the free duty calculation.

= 0.1.2 =
* Text domain now matches the plugin slug (plogins-customs) across all strings and the translation template, so wp.org language packs work.

= 0.1.1 =
* Restored the wp.org-aligned bootstrap file name, text domain and translation template for the `customs` slug.

= 0.1.0 =
* Initial release: EU flat import duty estimated and added as a cart and checkout fee, with configurable per-line amount, threshold, store origin, currency rate, tariff-line basis, label and taxable flag.
