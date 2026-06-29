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
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly EuMembership $eu,
        private readonly TariffLineCounter $counter,
    ) {
    }

    /**
     * Duty for the cart in the store currency, or 0.0 when it does not apply.
     *
     * @param \WC_Cart    $cart
     * @param string|null $destination ISO alpha-2 destination country. When null
     *                                 it is resolved from the current customer.
     */
    public function calculate(\WC_Cart $cart, ?string $destination = null): float
    {
        if (! $this->settings->isEnabled()) {
            return 0.0;
        }

        // Origin must be outside the EU (parcels shipped INTO the EU from
        // outside it, the UK included). An unknown origin cannot qualify.
        $origin = $this->settings->originCountry();
        if ('' === $origin || $this->eu->isMember($origin)) {
            return 0.0;
        }

        $destination ??= $this->destinationCountry();
        if ('' === $destination || ! $this->eu->isMember($destination)) {
            return 0.0;
        }

        $rate = $this->settings->eurRate();

        // Intrinsic goods value, excluding shipping, tax and fees. get_subtotal()
        // is the ex-tax sum of line subtotals. Compared in EUR via the rate.
        $goodsValueEur = (float) $cart->get_subtotal() / $rate;
        if ($goodsValueEur > $this->settings->thresholdEur()) {
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
     * Resolve the destination country from the current customer, preferring the
     * shipping country and falling back to billing.
     */
    private function destinationCountry(): string
    {
        $customer = WC()->customer ?? null;
        if (! $customer instanceof \WC_Customer) {
            return '';
        }

        $country = (string) $customer->get_shipping_country();
        if ('' === $country) {
            $country = (string) $customer->get_billing_country();
        }

        return strtoupper(trim($country));
    }
}
