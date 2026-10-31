<?php

declare(strict_types=1);

namespace Customs\Cart;

use Customs\Contract\HasHooks;
use Customs\Duty\DutyCalculator;
use Customs\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Applies the EU import duty in the way the merchant chose under "Who pays the
 * duty".
 *
 * Charge: hooks woocommerce_cart_calculate_fees and uses WC()->cart->add_fee(),
 * the single fee path shared by the classic cart/checkout and the Cart and
 * Checkout Blocks, so the line shows in both and is carried to the order (HPOS
 * included).
 *
 * Estimate and absorb: no fee. The amount is written to the order (classic and
 * Store API checkout), shown to the shopper as a row outside the total in
 * estimate mode, and shown to the merchant on the order screen in both.
 */
final class DutyFeeApplicator implements HasHooks
{
    public const META_AMOUNT = '_customs_duty_estimate';
    public const META_MODE   = '_customs_duty_mode';

    public function __construct(
        private readonly DutyCalculator $calculator,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('woocommerce_cart_calculate_fees', [$this, 'addDutyFee']);
        add_action('woocommerce_cart_totals_after_order_total', [$this, 'renderEstimateRow']);
        add_action('woocommerce_review_order_after_order_total', [$this, 'renderEstimateRow']);
        add_action('woocommerce_checkout_create_order', [$this, 'recordOnOrder']);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'recordOnStoreApiOrder']);
        add_filter('woocommerce_get_order_item_totals', [$this, 'addOrderTotalsRow'], 10, 2);
        add_action('woocommerce_admin_order_totals_after_total', [$this, 'renderAdminRow']);
    }

    /**
     * @param \WC_Cart $cart The cart WooCommerce passes to the fees hook.
     */
    public function addDutyFee($cart): void
    {
        if (SettingsRepository::MODE_CHARGE !== $this->settings->dutyMode()) {
            return;
        }

        $duty = $this->duty($cart);
        if ($duty > 0) {
            $cart->add_fee($this->settings->label(), $duty, $this->settings->isTaxable());
        }
    }

    /**
     * Classic cart and checkout totals: the estimate as a row below the total.
     */
    public function renderEstimateRow(): void
    {
        if (SettingsRepository::MODE_ESTIMATE !== $this->settings->dutyMode() || ! function_exists('WC')) {
            return;
        }

        $duty = $this->duty(WC()->cart);
        if ($duty <= 0) {
            return;
        }

        printf(
            '<tr class="customs-duty-estimate"><th>%1$s</th><td data-title="%1$s">%2$s<br /><small>%3$s</small></td></tr>',
            esc_html($this->settings->label()),
            wp_kses_post(wc_price($duty)),
            esc_html__('Not included in the total. Paid on delivery.', 'plogins-customs')
        );
    }

    /**
     * Classic checkout: the order is saved right after this hook.
     *
     * @param \WC_Order $order
     */
    public function recordOnOrder($order): void
    {
        if (! $order instanceof \WC_Order || SettingsRepository::MODE_CHARGE === $this->settings->dutyMode() || ! function_exists('WC')) {
            return;
        }

        $duty = $this->duty(WC()->cart);
        if ($duty <= 0) {
            return;
        }

        $order->update_meta_data(self::META_AMOUNT, (string) $duty);
        $order->update_meta_data(self::META_MODE, $this->settings->dutyMode());
    }

    /**
     * Store API checkout: the order was already saved, so save it again.
     *
     * @param \WC_Order $order
     */
    public function recordOnStoreApiOrder($order): void
    {
        $this->recordOnOrder($order);
        if ($order instanceof \WC_Order && '' !== (string) $order->get_meta(self::META_MODE)) {
            $order->save();
        }
    }

    /**
     * Order confirmation, My account and emails: the estimate below the total.
     *
     * @param array<string, array{label: string, value: string}> $rows
     * @param \WC_Order                                          $order
     * @return array<string, array{label: string, value: string}>
     */
    public function addOrderTotalsRow($rows, $order): array
    {
        $rows = is_array($rows) ? $rows : [];
        if (! $order instanceof \WC_Order || SettingsRepository::MODE_ESTIMATE !== $order->get_meta(self::META_MODE)) {
            return $rows;
        }

        $rows['customs_duty_estimate'] = [
            'label' => $this->settings->label() . ':',
            'value' => wc_price((float) $order->get_meta(self::META_AMOUNT), ['currency' => $order->get_currency()])
                . ' <small>' . esc_html__('Not included in the total. Paid on delivery.', 'plogins-customs') . '</small>',
        ];

        return $rows;
    }

    /**
     * Order edit screen (HPOS and legacy): the recorded amount and who pays it.
     *
     * @param int $orderId
     */
    public function renderAdminRow($orderId): void
    {
        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            return;
        }

        $mode = (string) $order->get_meta(self::META_MODE);
        if (! in_array($mode, [SettingsRepository::MODE_ESTIMATE, SettingsRepository::MODE_ABSORB], true)) {
            return;
        }

        printf(
            '<tr><td class="label">%1$s:</td><td width="1%%"></td><td class="total">%2$s<br /><small>%3$s</small></td></tr>',
            esc_html($this->settings->label()),
            wp_kses_post(wc_price((float) $order->get_meta(self::META_AMOUNT), ['currency' => $order->get_currency()])),
            esc_html(
                SettingsRepository::MODE_ABSORB === $mode
                    ? __('Absorbed by the store, not charged to the customer.', 'plogins-customs')
                    : __('Shown to the customer as paid on delivery, not charged.', 'plogins-customs')
            )
        );
    }

    /**
     * Duty for the cart, or 0.0 when the cart is unusable or the plugin is off.
     *
     * @param mixed $cart
     */
    private function duty($cart): float
    {
        if (! $cart instanceof \WC_Cart) {
            return 0.0;
        }

        // Skip admin requests that are not cart/checkout AJAX, to avoid running
        // during unrelated back-office cart operations.
        if (is_admin() && ! wp_doing_ajax()) {
            return 0.0;
        }

        if (! $this->settings->isEnabled()) {
            return 0.0;
        }

        return $this->calculator->calculate($cart);
    }
}
