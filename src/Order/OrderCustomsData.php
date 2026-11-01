<?php

declare(strict_types=1);

namespace Customs\Order;

use Customs\Cart\DutyFeeApplicator;
use Customs\Duty\TariffLineCounter;
use Customs\Geo\EuMembership;
use Customs\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Extracts structured customs declarations and paperwork data from a WooCommerce order.
 *
 * Produces the canonical dataset used by:
 * 1. Printable Commercial Invoice / Customs Declaration (CN22/CN23)
 * 2. Carrier CSV Export (DHL, FedEx, UPS, DPD, PostNL, Royal Mail, Poczta Polska)
 * 3. REST API endpoint (GET /wp-json/customs/v1/orders/<id>)
 */
final class OrderCustomsData
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly EuMembership $eu,
    ) {
    }

    /**
     * Build the structured customs dataset for an order.
     *
     * @param \WC_Order $order
     * @return array<string, mixed>
     */
    public function forOrder(\WC_Order $order): array
    {
        $originCountry = $this->settings->originCountry();
        $sender = [
            'name'     => (string) get_bloginfo('name'),
            'address1' => (string) get_option('woocommerce_store_address', ''),
            'address2' => (string) get_option('woocommerce_store_address_2', ''),
            'city'     => (string) get_option('woocommerce_store_city', ''),
            'state'    => $this->settings->originState(),
            'postcode' => $this->settings->originPostcode(),
            'country'  => $originCountry,
            'eori'     => $this->settings->eori(),
        ];

        $shippingCountry = (string) $order->get_shipping_country();
        $useBilling      = '' === $shippingCountry;

        $firstName = $useBilling ? (string) $order->get_billing_first_name() : (string) $order->get_shipping_first_name();
        $lastName  = $useBilling ? (string) $order->get_billing_last_name() : (string) $order->get_shipping_last_name();
        $name      = trim($firstName . ' ' . $lastName);

        $destCountry  = $useBilling ? (string) $order->get_billing_country() : $shippingCountry;
        $destPostcode = $useBilling ? (string) $order->get_billing_postcode() : (string) $order->get_shipping_postcode();
        $destState    = $useBilling ? (string) $order->get_billing_state() : (string) $order->get_shipping_state();

        $consignee = [
            'name'     => $name,
            'company'  => $useBilling ? (string) $order->get_billing_company() : (string) $order->get_shipping_company(),
            'address1' => $useBilling ? (string) $order->get_billing_address_1() : (string) $order->get_shipping_address_1(),
            'address2' => $useBilling ? (string) $order->get_billing_address_2() : (string) $order->get_shipping_address_2(),
            'city'     => $useBilling ? (string) $order->get_billing_city() : (string) $order->get_shipping_city(),
            'state'    => $destState,
            'postcode' => $destPostcode,
            'country'  => $destCountry,
            'phone'    => (string) $order->get_billing_phone(),
            'email'    => (string) $order->get_billing_email(),
        ];

        $isDestEu = $this->eu->isMember($destCountry, $destPostcode, $destState);

        $items          = [];
        $totalWeightKg  = 0.0;
        $totalValue     = 0.0;
        $totalQuantity  = 0;

        foreach ($order->get_items() as $item) {
            if (! $item instanceof \WC_Order_Item_Product) {
                continue;
            }

            $product = $item->get_product();
            if ($product instanceof \WC_Product && $product->is_virtual()) {
                continue;
            }

            $qty = max(1, (int) $item->get_quantity());
            $lineTotal = (float) $item->get_total();
            $unitValue = round($lineTotal / $qty, 4);

            $weightKg = 0.0;
            if ($product instanceof \WC_Product && $product->has_weight()) {
                $rawWeight = (float) $product->get_weight();
                $weightKg  = function_exists('wc_get_weight') ? (float) wc_get_weight($rawWeight, 'kg') : $rawWeight;
            }
            $lineWeightKg = round($weightKg * $qty, 4);

            $customsDesc = $this->resolveCustomsDesc($product, $item->get_name());
            $hsCode      = $this->resolveHsCode($product);
            $itemOrigin  = $this->resolveOrigin($product);

            $items[] = [
                'item_id'         => $item->get_id(),
                'product_id'      => $item->get_product_id(),
                'variation_id'    => $item->get_variation_id(),
                'name'            => $item->get_name(),
                'sku'             => $product instanceof \WC_Product ? (string) $product->get_sku() : '',
                'customs_desc'    => $customsDesc,
                'hs_code'         => $hsCode,
                'origin_country'  => $itemOrigin,
                'quantity'        => $qty,
                'unit_weight_kg'  => $weightKg,
                'total_weight_kg' => $lineWeightKg,
                'unit_value'      => $unitValue,
                'total_value'     => $lineTotal,
            ];

            $totalWeightKg += $lineWeightKg;
            $totalValue    += $lineTotal;
            $totalQuantity += $qty;
        }

        $duty = $this->resolveDuty($order);

        $orderDate = '';
        $dateCreated = $order->get_date_created();
        if ($dateCreated instanceof \DateTimeInterface) {
            $orderDate = $dateCreated->format('Y-m-d H:i:s');
        }

        return [
            'order_id'         => $order->get_id(),
            'order_number'     => $order->get_order_number(),
            'order_date'       => $orderDate,
            'currency'         => $order->get_currency(),
            'is_eu_dest'       => $isDestEu,
            'sender'           => $sender,
            'consignee'        => $consignee,
            'duty'             => $duty,
            'items'            => $items,
            'total_items_qty'  => $totalQuantity,
            'total_weight_kg'  => round($totalWeightKg, 4),
            'total_goods_val'  => round($totalValue, 2),
            'order_total'      => (float) $order->get_total(),
        ];
    }

    private function resolveCustomsDesc(?\WC_Product $product, string $fallbackName): string
    {
        if (! $product instanceof \WC_Product) {
            return $fallbackName;
        }

        $desc = (string) $product->get_meta(TariffLineCounter::DESC_META_KEY);
        if ('' !== trim($desc)) {
            return trim($desc);
        }

        $parentId = $product->get_parent_id();
        if ($parentId > 0) {
            $parent = wc_get_product($parentId);
            if ($parent instanceof \WC_Product) {
                $parentDesc = (string) $parent->get_meta(TariffLineCounter::DESC_META_KEY);
                if ('' !== trim($parentDesc)) {
                    return trim($parentDesc);
                }
            }
        }

        return $fallbackName;
    }

    private function resolveHsCode(?\WC_Product $product): string
    {
        if (! $product instanceof \WC_Product) {
            return '';
        }

        $code = (string) $product->get_meta(TariffLineCounter::META_KEY);
        if ('' !== trim($code)) {
            return trim($code);
        }

        $parentId = $product->get_parent_id();
        if ($parentId > 0) {
            $parent = wc_get_product($parentId);
            if ($parent instanceof \WC_Product) {
                return trim((string) $parent->get_meta(TariffLineCounter::META_KEY));
            }
        }

        return '';
    }

    private function resolveOrigin(?\WC_Product $product): string
    {
        if ($product instanceof \WC_Product) {
            $origin = SettingsRepository::sanitizeCountry((string) $product->get_meta(TariffLineCounter::ORIGIN_META_KEY));
            if ('' !== $origin) {
                return $origin;
            }

            $parentId = $product->get_parent_id();
            if ($parentId > 0) {
                $parent = wc_get_product($parentId);
                if ($parent instanceof \WC_Product) {
                    $parentOrigin = SettingsRepository::sanitizeCountry((string) $parent->get_meta(TariffLineCounter::ORIGIN_META_KEY));
                    if ('' !== $parentOrigin) {
                        return $parentOrigin;
                    }
                }
            }
        }

        $defaultOrigin = $this->settings->goodsOrigin();
        if ('' !== $defaultOrigin) {
            return $defaultOrigin;
        }

        return $this->settings->originCountry();
    }

    /**
     * @return array{amount: float, mode: string, label: string}
     */
    private function resolveDuty(\WC_Order $order): array
    {
        // 1. Check charged fee lines on the order.
        $label = $this->settings->label();
        foreach ($order->get_fees() as $fee) {
            if ($fee->get_name() === $label) {
                return [
                    'amount' => (float) $fee->get_total(),
                    'mode'   => SettingsRepository::MODE_CHARGE,
                    'label'  => $label,
                ];
            }
        }

        // 2. Check estimate/absorb order meta.
        $metaAmount = (string) $order->get_meta(DutyFeeApplicator::META_AMOUNT);
        $metaMode   = (string) $order->get_meta(DutyFeeApplicator::META_MODE);

        if ('' !== $metaAmount && is_numeric($metaAmount)) {
            return [
                'amount' => (float) $metaAmount,
                'mode'   => in_array($metaMode, [SettingsRepository::MODE_ESTIMATE, SettingsRepository::MODE_ABSORB], true) ? $metaMode : SettingsRepository::MODE_CHARGE,
                'label'  => $label,
            ];
        }

        return [
            'amount' => 0.0,
            'mode'   => 'none',
            'label'  => $label,
        ];
    }
}
