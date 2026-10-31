<?php
/**
 * Default settings, merged under the option key `customs_settings`.
 *
 * Amounts and the threshold are expressed in EUR, because the EU rule is
 * defined in EUR (a flat 3 EUR per tariff line, on goods up to 150 EUR). When
 * the store currency is not EUR, `eur_rate` converts those EUR figures into the
 * store currency at calculation time. See Customs\Settings\SettingsRepository.
 *
 * @package Customs
 *
 * @return array<string, mixed>
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

return [
    // Master switch.
    'enabled'        => true,
    // Flat duty charged per distinct tariff line, in EUR (EU rule: 3 EUR).
    'per_line'       => 3.0,
    // Goods-value ceiling the duty applies under, in EUR (EU rule: 150 EUR).
    'threshold'      => 150.0,
    // Store-currency units per 1 EUR. 1.0 when the store already sells in EUR.
    'eur_rate'       => 1.0,
    // Origin country (ISO-3166 alpha-2). Empty string falls back to the
    // WooCommerce base country at runtime.
    'origin_country' => '',
    // How a cart line's tariff line is decided when no explicit code is set on
    // the product or its category: 'category' (default, one line per distinct
    // product category) or 'product' (one line per distinct product).
    'count_basis'    => 'category',
    // Whether a subcategory counts under the category it sits in. Off by
    // default because it cuts both ways: a shop whose Books split into Health
    // and Self improvement wants one line, and a shop whose Other Products
    // split into Beads and Pictures wants two, and the taxonomy cannot tell
    // the two apart. A tariff code can, and always wins over either.
    'group_subcategories' => false,
    // Customer-facing label for the duty line on cart, checkout and order.
    // Empty on purpose. A value here is not a gettext call, so it never reaches
    // the .pot and no language pack can translate it, and once the settings
    // screen is saved the English is frozen into the option. Empty means "use
    // Customs\Service\Texts", which is translated; a merchant's own label still
    // wins.
    'label'          => '',
    // Whether WooCommerce should apply tax on top of the duty fee. The duty is
    // a customs charge, so the default is off (added as its own untaxed line).
    'taxable'        => false,
    // Charge only inside the period Council Regulation (EU) 2026/382, Article 2
    // sets: from 1 July 2026 until 1 July 2028. Untick if that period is extended.
    'enforce_dates'  => true,
    // Who pays the duty. 'charge' adds it to the order total (DDP). 'estimate'
    // shows it to the shopper without charging it, for parcels where the
    // carrier collects it on delivery (DAP). 'absorb' charges nothing and only
    // records the amount on the order, for a store that pays it itself.
    'duty_mode'      => 'charge',
    // Default country of origin of the goods (ISO-3166 alpha-2), used for any
    // product without its own. Empty means no origin is counted.
    'goods_origin'   => '',
];
