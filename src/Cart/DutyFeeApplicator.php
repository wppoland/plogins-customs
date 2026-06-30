<?php

declare(strict_types=1);

namespace Plogins\Customs\Cart;

use Plogins\Customs\Contract\HasHooks;
use Plogins\Customs\Duty\DutyCalculator;
use Plogins\Customs\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Adds the EU import duty to the cart as its own pre-tax fee line.
 *
 * Hooks woocommerce_cart_calculate_fees and uses WC()->cart->add_fee(), which
 * is the single fee path shared by the classic shortcode cart/checkout and the
 * Cart and Checkout Blocks, so the line shows consistently in both and the
 * amount is carried through to the order (HPOS included).
 */
final class DutyFeeApplicator implements HasHooks
{
    public function __construct(
        private readonly DutyCalculator $calculator,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_cart_calculate_fees', [$this, 'addDutyFee']);
    }

    /**
     * @param \WC_Cart $cart The cart WooCommerce passes to the fees hook.
     */
    public function addDutyFee($cart): void
    {
        if (! $cart instanceof \WC_Cart) {
            return;
        }

        // Skip admin requests that are not cart/checkout AJAX, to avoid running
        // during unrelated back-office cart operations.
        if (is_admin() && ! wp_doing_ajax()) {
            return;
        }

        if (! $this->settings->isEnabled()) {
            return;
        }

        $duty = $this->calculator->calculate($cart);
        if ($duty <= 0) {
            return;
        }

        $cart->add_fee($this->settings->label(), $duty, $this->settings->isTaxable());
    }
}
