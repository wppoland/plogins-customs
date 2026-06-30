<?php

declare(strict_types=1);

namespace Plogins\Customs\Admin;

use Plogins\Customs\Contract\HasHooks;
use Plogins\Customs\Duty\TariffLineCounter;

defined('ABSPATH') || exit;

/**
 * Adds an optional "Customs tariff code" field to the product editor.
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
    }

    /**
     * @param int $post_id Product post ID.
     */
    public function save($post_id): void
    {
        $post_id = (int) $post_id;

        // woocommerce_process_product_meta runs after WooCommerce has verified
        // the product editor nonce and the user's edit_product capability.
        if (! current_user_can('edit_product', $post_id)) {
            return;
        }

        $product = wc_get_product($post_id);
        if (! $product instanceof \WC_Product) {
            return;
        }

        // woocommerce_process_product_meta fires only after WooCommerce verifies the
        // product editor nonce and capability; the value is sanitized on the next line.
        $raw  = isset($_POST[TariffLineCounter::META_KEY]) ? wp_unslash($_POST[TariffLineCounter::META_KEY]) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $code = sanitize_text_field(is_string($raw) ? $raw : '');

        if ('' === $code) {
            $product->delete_meta_data(TariffLineCounter::META_KEY);
        } else {
            $product->update_meta_data(TariffLineCounter::META_KEY, $code);
        }

        $product->save();
    }
}
