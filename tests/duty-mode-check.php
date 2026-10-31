<?php
/**
 * Self-check for "Who pays the duty": charge adds the fee, estimate and absorb
 * never do, and both record the amount on the order instead.
 *
 * A DAP merchant whose carrier collects the duty on delivery would otherwise
 * charge the shopper twice.
 *
 * Run: php tests/duty-mode-check.php
 *
 * @package plogins-customs
 */

namespace {
    define('ABSPATH', __DIR__);
    define('CUSTOMS_DIR', __DIR__ . '/../');

    $GLOBALS['mode'] = 'charge';

    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
    function apply_filters(string $hook, $value, ...$args)
    {
        return $value;
    }
    function is_admin(): bool
    {
        return false;
    }
    function wp_doing_ajax(): bool
    {
        return false;
    }
    function wc_price($amount, array $args = []): string
    {
        return number_format((float) $amount, 2);
    }
    function get_option(string $name, $default = false)
    {
        return ['duty_mode' => $GLOBALS['mode']];
    }

    class WC_Cart
    {
        /** @var list<float> */
        public array $fees = [];

        public function add_fee(string $name, float $amount, bool $taxable = false): void
        {
            $this->fees[] = $amount;
        }
    }

    class WC_Order
    {
        /** @var array<string, string> */
        public array $meta = [];

        public function update_meta_data(string $key, $value): void
        {
            $this->meta[$key] = (string) $value;
        }

        public function get_meta(string $key)
        {
            return $this->meta[$key] ?? '';
        }

        public function get_currency(): string
        {
            return 'EUR';
        }
    }

    function WC(): object
    {
        return $GLOBALS['wc'];
    }
}

namespace Customs\Duty {
    final class DutyCalculator
    {
        public function calculate(\WC_Cart $cart): float
        {
            return 6.0;
        }
    }
}

namespace {
    require __DIR__ . '/../src/Contract/HasHooks.php';
    require __DIR__ . '/../src/Service/Texts.php';
    require __DIR__ . '/../src/Settings/SettingsRepository.php';
    require __DIR__ . '/../src/Cart/DutyFeeApplicator.php';

    use Customs\Cart\DutyFeeApplicator;
    use Customs\Duty\DutyCalculator;
    use Customs\Settings\SettingsRepository;

    $applicator = new DutyFeeApplicator(new DutyCalculator(), new SettingsRepository());

    $check = static function (string $label, bool $ok): void {
        if (! $ok) {
            fwrite(STDERR, "FAIL {$label}\n");
            exit(1);
        }
        echo "ok   {$label}\n";
    };

    foreach (['charge' => 1, 'estimate' => 0, 'absorb' => 0] as $mode => $fees) {
        $GLOBALS['mode'] = $mode;
        $cart            = new WC_Cart();
        $GLOBALS['wc']   = (object) ['cart' => $cart];

        $applicator->addDutyFee($cart);
        $check("{$mode}: {$fees} fee line(s) on the cart", count($cart->fees) === $fees);

        $order = new WC_Order();
        $applicator->recordOnOrder($order);
        $recorded = 'charge' === $mode ? '' : '6';
        $check("{$mode}: order records amount '{$recorded}'", $order->get_meta('_customs_duty_estimate') === $recorded);

        $rows = $applicator->addOrderTotalsRow([], $order);
        $check("{$mode}: shopper sees an estimate row only in estimate mode", isset($rows['customs_duty_estimate']) === ('estimate' === $mode));
    }

    $GLOBALS['mode'] = 'bogus';
    $check('an unknown stored mode falls back to charge', (new SettingsRepository())->dutyMode() === 'charge');

    echo "all duty mode checks passed\n";
}
