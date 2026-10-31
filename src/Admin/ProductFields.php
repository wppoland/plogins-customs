<?php

declare(strict_types=1);

namespace Customs\Admin;

use Customs\Contract\HasHooks;
use Customs\Duty\TariffLineCounter;
use Customs\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Adds an optional "Customs tariff code" field and an "Outside the 3 EUR duty"
 * checkbox to the product editor.
 *
 * The code is a free-text hint (a short HS heading or any grouping label) that,
 * when set, overrides the category/product grouping used to count tariff lines.
 * It is intentionally simple for the FREE MVP: a single field on the product's
 * Shipping tab. Variation-level codes and a managed HS-code list are left to
 * Customs Pro.
 */
final class ProductFields implements HasHooks
{
    public function registerHooks(): void
    {
        add_action('woocommerce_product_options_shipping', [$this, 'render']);
        add_action('woocommerce_process_product_meta', [$this, 'save']);
    }

    public function render(): void
    {
        woocommerce_wp_text_input([
            'id'          => TariffLineCounter::META_KEY,
            'label'       => __('Customs tariff code', 'plogins-customs'),
            'desc_tip'    => true,
            'description' => __('Optional. Products sharing a code count as one EU import duty line. Leave empty to group by category.', 'plogins-customs'),
            'placeholder' => __('e.g. 6109 (T-shirts)', 'plogins-customs'),
        ]);

        $countries = function_exists('WC') && WC()->countries instanceof \WC_Countries ? WC()->countries->get_countries() : [];

        woocommerce_wp_select([
            'id'          => TariffLineCounter::ORIGIN_META_KEY,
            'label'       => __('Country of origin', 'plogins-customs'),
            'desc_tip'    => true,
            'description' => __('Where the goods were made. Products with the same tariff line but a different origin count as separate duty lines. Leave on the store default to use the one set under WooCommerce, EU Import Duty.', 'plogins-customs'),
            'options'     => ['' => __('Store default', 'plogins-customs')] + (is_array($countries) ? $countries : []),
        ]);

        woocommerce_wp_checkbox([
            'id'          => TariffLineCounter::EXEMPT_META_KEY,
            'label'       => __('Outside the 3 EUR duty', 'plogins-customs'),
            'description' => __('Tick for goods that claim a preferential tariff, for example under a trade agreement, and are not imported under IOSS. They add no duty line. Their value still counts toward the goods-value threshold.', 'plogins-customs'),
        ]);
    }

    /**
     * @param int $post_id Product post ID.
     */
    public function save($post_id): void
    {
        $post_id = (int) $post_id;

        // The product editor's own nonce field is verified here, before any
        // field is read.
        if (! isset($_POST['woocommerce_meta_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data')) {
            return;
        }

        if (! current_user_can('edit_product', $post_id)) {
            return;
        }

        $product = wc_get_product($post_id);
        if (! $product instanceof \WC_Product) {
            return;
        }

        $code = isset($_POST[TariffLineCounter::META_KEY]) && is_string($_POST[TariffLineCounter::META_KEY])
            ? sanitize_text_field(wp_unslash($_POST[TariffLineCounter::META_KEY]))
            : '';

        if ('' === $code) {
            $product->delete_meta_data(TariffLineCounter::META_KEY);
        } else {
            $product->update_meta_data(TariffLineCounter::META_KEY, $code);
        }

        $origin = isset($_POST[TariffLineCounter::ORIGIN_META_KEY]) && is_string($_POST[TariffLineCounter::ORIGIN_META_KEY])
            ? SettingsRepository::sanitizeCountry(sanitize_text_field(wp_unslash($_POST[TariffLineCounter::ORIGIN_META_KEY])))
            : '';

        if ('' === $origin) {
            $product->delete_meta_data(TariffLineCounter::ORIGIN_META_KEY);
        } else {
            $product->update_meta_data(TariffLineCounter::ORIGIN_META_KEY, $origin);
        }

        if (isset($_POST[TariffLineCounter::EXEMPT_META_KEY])) {
            $product->update_meta_data(TariffLineCounter::EXEMPT_META_KEY, 'yes');
        } else {
            $product->delete_meta_data(TariffLineCounter::EXEMPT_META_KEY);
        }

        $product->save();
    }
}
