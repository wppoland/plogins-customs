<?php
/**
 * Default settings, merged under the option key `customs_settings`.
 *
 * Amounts and the threshold are expressed in EUR, because the EU rule is
 * defined in EUR (a flat 3 EUR per tariff line, on goods up to 150 EUR). When
 * the store currency is not EUR, `eur_rate` converts those EUR figures into the
 * store currency at calculation time. See Plogins\Customs\Settings\SettingsRepository.
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
    // Customer-facing label for the duty line on cart, checkout and order.
    'label'          => 'EU import duty (estimate)',
    // Whether WooCommerce should apply tax on top of the duty fee. The duty is
    // a customs charge, so the default is off (added as its own untaxed line).
    'taxable'        => false,
];
