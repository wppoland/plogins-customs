<?php

declare(strict_types=1);

namespace Customs\Duty;

use Customs\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Counts the distinct "tariff lines" in a cart.
 *
 * The EU rule charges the flat duty per distinct tariff classification in a
 * consignment, not per unit: three identical shirts are one line, while a shirt
 * plus a lipstick are two. Real tariff classification (HS codes) is out of
 * scope for the FREE MVP, so a tariff line is approximated by one of:
 *
 *   1. an explicit tariff code set on the product or variation
 *      (meta key _customs_tariff_code), when present;
 *   2. otherwise, the product's assigned category (default basis), or the
 *      top-level category it sits under when the merchant has asked for that; or
 *   3. otherwise, the product itself (product basis, or category fallback when
 *      a product has no category).
 *
 * Mapping precise HS codes and per-line lookups is left to Customs Pro.
 */
final class TariffLineCounter
{
    public const META_KEY = '_customs_tariff_code';

    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /**
     * Number of distinct tariff lines in the given cart.
     *
     * @param \WC_Cart $cart
     */
    public function count(\WC_Cart $cart): int
    {
        $basis = $this->settings->countBasis();
        $keys  = [];

        foreach ($cart->get_cart() as $item) {
            if (! is_array($item)) {
                continue;
            }

            $product = $item['data'] ?? null;
            if (! $product instanceof \WC_Product) {
                continue;
            }

            // A virtual or downloadable product never travels in the parcel, so
            // it is no tariff line. A cart of only such products counts zero and
            // DutyCalculator adds no duty at all.
            if (! $product->needs_shipping()) {
                continue;
            }

            $keys[$this->lineKey($product, $basis)] = true;
        }

        $count = count($keys);

        /**
         * Filters the number of distinct tariff lines counted for a cart.
         *
         * @param int      $count Distinct tariff lines.
         * @param \WC_Cart $cart  The cart being evaluated.
         * @param string   $basis The active count basis (category|product).
         */
        $count = (int) apply_filters('customs/tariff_line_count', $count, $cart, $basis);

        return max(0, $count);
    }

    /**
     * Resolve a stable grouping key for a single product line.
     */
    private function lineKey(\WC_Product $product, string $basis): string
    {
        $key = $this->resolveKey($product, $basis);

        /**
         * Filters the grouping key for a single cart line.
         *
         * Two products that resolve to the same key count as one tariff line.
         * Use this to classify by something the plugin does not know about, such
         * as a real HS heading held in another plugin's meta.
         *
         * @param string      $key     The resolved grouping key.
         * @param \WC_Product $product The product being keyed.
         * @param string      $basis   The active count basis (category|product).
         */
        return (string) apply_filters('customs/tariff_line_key', $key, $product, $basis);
    }

    private function resolveKey(\WC_Product $product, string $basis): string
    {
        $code = $this->explicitCode($product);
        if ('' !== $code) {
            return 'code:' . $code;
        }

        if (SettingsRepository::BASIS_CATEGORY === $basis) {
            $category = $this->groupCategoryId($product);
            if ($category > 0) {
                return 'cat:' . $category;
            }
        }

        // Product basis, or category basis with no category assigned. Variations
        // group under their parent so size/colour variants count as one line.
        $parent = $product->get_parent_id();

        return 'prod:' . ($parent > 0 ? $parent : $product->get_id());
    }

    /**
     * Explicit tariff code stored on the variation or its parent product.
     *
     * Tries three lookup paths so that a code that was saved correctly is never
     * missed:
     *   1. the WC_Data meta API on the product/variation itself;
     *   2. a direct get_post_meta() read for the same post id, bypassing any
     *      CRUD filters or cached copies;
     *   3. the same two reads on the parent variable product, when the item is
     *      a variation and did not carry its own code.
     *
     * Whichever path returns a non-empty string wins. The returned value is
     * normalised (trimmed, upper-cased, punctuation collapsed) so that
     * "4901.90", "4901-90" and " 4901 90 " are treated as the same code.
     */
    private function explicitCode(\WC_Product $product): string
    {
        $code = $this->readNormalisedCode($product);
        if ('' !== $code) {
            return $code;
        }

        $parent = $product->get_parent_id();
        if ($parent > 0) {
            $parentProduct = wc_get_product($parent);
            if ($parentProduct instanceof \WC_Product) {
                $code = $this->readNormalisedCode($parentProduct);
                if ('' !== $code) {
                    return $code;
                }
            }
        }

        return '';
    }

    /**
     * Normalised tariff code for a single product, reading both the WC_Data
     * meta API and the underlying post_meta table.
     */
    private function readNormalisedCode(\WC_Product $product): string
    {
        $readers = [
            static fn (): string => trim((string) $product->get_meta(self::META_KEY, true)),
            static function () use ($product): string {
                $pid = $product->get_id();
                if ($pid <= 0) {
                    return '';
                }
                $raw = get_post_meta($pid, self::META_KEY, true);
                if (! is_scalar($raw) && null !== $raw && ! is_array($raw)) {
                    return '';
                }
                if (is_array($raw)) {
                    $raw = reset($raw);
                    if (! is_scalar($raw)) {
                        return '';
                    }
                }
                return trim((string) $raw);
            },
        ];

        foreach ($readers as $read) {
            $value = $read();
            if ('' === $value) {
                continue;
            }
            $normalised = $this->normaliseCode($value);
            if ('' !== $normalised) {
                return $normalised;
            }
        }

        return '';
    }

    /**
     * Canonical form for a tariff code so that formatting differences do not
     * split what should be one grouping key.
     *
     * Upper-cased so codes are compared case-insensitively. Runs of anything
     * that is not a letter or digit are collapsed to a single dot, because
     * "4901.90.00", "4901-90-00" and "4901 90 00" all describe the same
     * heading for the plugin's line-counting purposes. An empty result means
     * the caller should fall back to the category/product key.
     */
    private function normaliseCode(string $code): string
    {
        $code = trim($code);
        if ('' === $code) {
            return '';
        }

        $code = strtoupper($code);
        $code = preg_replace('/[^A-Z0-9]+/', '.', $code);
        $code = trim((string) $code, '.');

        return $code;
    }

    /**
     * The category a product groups under.
     *
     * By default this is the assigned category itself, so Other Products >
     * Beads and Other Products > Pictures count as two lines. Turn on
     * "group subcategories" and each assigned category is walked up to its own
     * top-level ancestor instead, so Books > Health and Books > Self
     * improvement count as one.
     *
     * Both readings are right for some shop and wrong for another, and the
     * taxonomy carries nothing that tells them apart, which is why this is a
     * setting rather than a rule, and why a tariff code beats it either way.
     *
     * Whichever mode is on, the lowest id wins, so the answer does not depend
     * on which term WooCommerce happens to return first.
     */
    private function groupCategoryId(\WC_Product $product): int
    {
        $ids = $product->get_category_ids();
        if (empty($ids) && $product->get_parent_id() > 0) {
            $parentProduct = wc_get_product($product->get_parent_id());
            if ($parentProduct instanceof \WC_Product) {
                $ids = $parentProduct->get_category_ids();
            }
        }

        if (empty($ids)) {
            return 0;
        }

        if (! $this->settings->groupSubcategories()) {
            // The assigned category as WooCommerce hands it over. Not the lowest
            // id: 1.0.10 used the first and 1.0.12 quietly switched to the
            // lowest, which is a different answer for any product carrying more
            // than one category, so a shop that never touched the new setting
            // still had its duty change under it. array_values() first so that
            // reset() on a numerically-sparse array does not pick an internal
            // pointer state that a previous caller left behind.
            $ordered = array_values($ids);

            return (int) $ordered[0];
        }

        $tops = [];

        foreach ($ids as $id) {
            $top = $this->topAncestorId((int) $id);
            if ($top > 0) {
                $tops[] = $top;
            }
        }

        if ([] === $tops) {
            return 0;
        }

        // Here the lowest id is deliberate: several assigned categories can roll
        // up to different ancestors, and the answer must not depend on term order.
        sort($tops, SORT_NUMERIC);

        return (int) $tops[0];
    }

    /**
     * The outermost ancestor of a product category, or the term itself when it
     * is already top level.
     */
    private function topAncestorId(int $termId): int
    {
        if ($termId <= 0) {
            return 0;
        }

        $ancestors = get_ancestors($termId, 'product_cat', 'taxonomy');

        // get_ancestors() returns nearest first, so the outermost is last.
        if (is_array($ancestors) && [] !== $ancestors) {
            return (int) end($ancestors);
        }

        return $termId;
    }
}
