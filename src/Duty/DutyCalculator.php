<?php

declare(strict_types=1);

namespace Customs\Duty;

use Customs\Geo\EuMembership;
use Customs\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Works out the EU import duty for a cart, in the store currency.
 *
 * The duty applies only when every condition holds:
 *   - the feature is enabled;
 *   - today falls between 1 July 2026 and 1 July 2028 (Council Regulation (EU)
 *     2026/382, Article 2), unless the merchant switched that check off;
 *   - the store origin country is OUTSIDE the EU;
 *   - the destination (shipping) country is INSIDE the EU; and
 *   - the intrinsic goods value is at or below the threshold (150 EUR).
 *
 * When it applies, the duty is: distinct tariff lines x per-line amount. Amounts
 * and the threshold are held in EUR (the unit the EU rule is written in) and
 * converted to the store currency through the configured eur_rate. VAT and
 * national handling fees are out of scope; the duty is added on top of them.
 */
final class DutyCalculator
{
    /** 1 July 2026 00:00 Brussels time, when the 3 EUR duty starts. */
    public const WINDOW_START = '2026-07-01 00:00:00';

    /** 1 July 2028 00:00 Brussels time, when it ends (exclusive). */
    public const WINDOW_END = '2028-07-01 00:00:00';

    /** @var \Closure(): int */
    private \Closure $clock;

    /**
     * @param \Closure(): int|null $clock Unix time source, injectable for tests.
     */
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly EuMembership $eu,
        private readonly TariffLineCounter $counter,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): int => time();
    }

    /**
     * Whether a Unix time falls inside the legal period of the 3 EUR duty.
     */
    public static function inWindow(int $now): bool
    {
        $tz = new \DateTimeZone('Europe/Brussels');

        return $now >= (new \DateTimeImmutable(self::WINDOW_START, $tz))->getTimestamp()
            && $now < (new \DateTimeImmutable(self::WINDOW_END, $tz))->getTimestamp();
    }

    /**
     * Duty for the cart in the store currency, or 0.0 when it does not apply.
     *
     * @param \WC_Cart    $cart
     * @param string|null $destination ISO alpha-2 destination country. When null
     *                                 it is resolved from the current customer,
     *                                 with the postcode.
     * @param string      $postcode    Destination postcode, when $destination is
     *                                 given.
     * @param string      $state       Destination WooCommerce state code, when
     *                                 $destination is given.
     */
    public function calculate(\WC_Cart $cart, ?string $destination = null, string $postcode = '', string $state = ''): float
    {
        if (! $this->settings->isEnabled()) {
            return 0.0;
        }

        if ($this->settings->enforceDates() && ! self::inWindow(($this->clock)())) {
            return 0.0;
        }

        // Origin must be outside the EU customs territory (parcels shipped INTO
        // it from outside, the UK, Ceuta and Melilla included). An unknown
        // origin cannot qualify.
        $origin = $this->settings->originCountry();
        if ('' === $origin || $this->eu->isMember($origin, $this->settings->originPostcode(), $this->settings->originState())) {
            return 0.0;
        }

        if (null === $destination) {
            [$destination, $postcode, $state] = $this->destination();
        }
        if ('' === $destination || ! $this->eu->isMember($destination, $postcode, $state)) {
            return 0.0;
        }

        $rate = $this->settings->eurRate();
        if ($rate <= 0.0) {
            return 0.0;
        }

        // Intrinsic goods value: net discounted item total (after coupons, excluding
        // VAT, shipping, insurance, and fees) per UCC Art. 1(42) / IOSS rules.
        // Falls back to get_subtotal() if cart totals have not been calculated yet.
        $goodsValue = (float) $cart->get_cart_contents_total();
        if ($goodsValue <= 0.0) {
            $goodsValue = (float) $cart->get_subtotal();
        }

        /**
         * Filters the intrinsic goods value in store currency before the threshold check.
         *
         * @param float    $goodsValue Intrinsic goods value in store currency.
         * @param \WC_Cart $cart       The cart being evaluated.
         */
        $goodsValue = (float) apply_filters('customs/intrinsic_goods_value', $goodsValue, $cart);

        if (($goodsValue / $rate) > $this->settings->thresholdEur()) {
            return 0.0;
        }

        $lines = $this->counter->count($cart);
        if ($lines < 1) {
            return 0.0;
        }

        $dutyStore = $this->settings->perLineEur() * $lines * $rate;
        $dutyStore = (float) wc_format_decimal($dutyStore, wc_get_price_decimals());

        /**
         * Filters the final duty amount in the store currency.
         *
         * @param float    $dutyStore   Duty in store currency.
         * @param int      $lines       Distinct tariff lines counted.
         * @param string   $destination Destination country code.
         * @param \WC_Cart $cart        The cart being evaluated.
         */
        $dutyStore = (float) apply_filters('customs/duty_amount', $dutyStore, $lines, $destination, $cart);

        return max(0.0, $dutyStore);
    }

    /**
     * Resolve the destination country, postcode and state from the current
     * customer, preferring the shipping address and falling back to billing.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function destination(): array
    {
        if (! function_exists('WC')) {
            return ['', '', ''];
        }

        $customer = WC()->customer;
        if (! $customer instanceof \WC_Customer) {
            return ['', '', ''];
        }

        $country  = (string) $customer->get_shipping_country();
        $postcode = (string) $customer->get_shipping_postcode();
        $state    = (string) $customer->get_shipping_state();
        if ('' === $country) {
            $country  = (string) $customer->get_billing_country();
            $postcode = (string) $customer->get_billing_postcode();
            $state    = (string) $customer->get_billing_state();
        }

        return [strtoupper(trim($country)), $postcode, $state];
    }
}
