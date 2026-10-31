<?php
/**
 * Self-check for DutyCalculator intrinsic goods value and EUR rate checks.
 *
 * Run: php tests/duty-calculator-check.php
 *
 * @package plogins-customs
 */

namespace {
    define('ABSPATH', __DIR__);
    define('CUSTOMS_DIR', __DIR__ . '/../');

    if (! function_exists('__')) {
        function __(string $text, string $domain = 'default'): string
        {
            return $text;
        }
    }

    if (! function_exists('wc_format_decimal')) {
        function wc_format_decimal($val, $decimals = 2): string
        {
            return number_format((float) $val, (int) $decimals, '.', '');
        }
    }

    if (! function_exists('wc_get_price_decimals')) {
        function wc_get_price_decimals(): int
        {
            return 2;
        }
    }

    $GLOBALS['wp_filters'] = [];

    if (! function_exists('add_filter')) {
        function add_filter(string $hook, callable $callback): void
        {
            $GLOBALS['wp_filters'][$hook][] = $callback;
        }
    }

    if (! function_exists('apply_filters')) {
        function apply_filters(string $hook, $value, ...$args)
        {
            if (isset($GLOBALS['wp_filters'][$hook])) {
                foreach ($GLOBALS['wp_filters'][$hook] as $cb) {
                    $value = $cb($value, ...$args);
                }
            }
            return $value;
        }
    }
}

namespace Customs\Settings {
    class SettingsRepository
    {
        public bool $enabled = true;
        public string $origin = 'US';
        public float $rate = 1.0;
        public float $threshold = 150.0;
        public float $perLine = 10.0;
        public bool $enforceDates = false;
        public string $originPostcode = '';

        public function enforceDates(): bool
        {
            return $this->enforceDates;
        }

        public function originPostcode(): string
        {
            return $this->originPostcode;
        }

        public function isEnabled(): bool
        {
            return $this->enabled;
        }

        public function originCountry(): string
        {
            return $this->origin;
        }

        public function eurRate(): float
        {
            return $this->rate;
        }

        public function thresholdEur(): float
        {
            return $this->threshold;
        }

        public function perLineEur(): float
        {
            return $this->perLine;
        }
    }
}

namespace Customs\Duty {
    class MockCart
    {
        public function __construct(
            private float $subtotal = 0.0,
            private float $cartContentsTotal = 0.0,
        ) {
        }

        public function get_subtotal(): float
        {
            return $this->subtotal;
        }

        public function get_cart_contents_total(): float
        {
            return $this->cartContentsTotal;
        }

        public function get_cart(): array
        {
            return [];
        }
    }

    class_alias(MockCart::class, 'WC_Cart');

    class TariffLineCounter
    {
        public function count(\WC_Cart $cart): int
        {
            return 1;
        }
    }

    require_once __DIR__ . '/../src/Geo/EuMembership.php';
    require_once __DIR__ . '/../src/Duty/DutyCalculator.php';

    use Customs\Geo\EuMembership;
    use Customs\Settings\SettingsRepository;

    $assert = static function (string $label, float $got, float $want): void {
        if (abs($got - $want) > 0.0001) {
            fwrite(STDERR, sprintf("FAIL %s: got %.4f, want %.4f\n", $label, $got, $want));
            exit(1);
        }
        printf("ok   %s (%.2f)\n", $label, $got);
    };

    $settings   = new SettingsRepository();
    $eu         = new EuMembership();
    $counter    = new TariffLineCounter();
    $calculator = new DutyCalculator($settings, $eu, $counter);

    // Check 1: Rate <= 0 guards against division by zero and returns 0.0
    $settings->rate = 0.0;
    $cart = new MockCart(subtotal: 100.0, cartContentsTotal: 80.0);
    $assert('zero rate returns 0.0 duty', $calculator->calculate($cart, 'DE'), 0.0);

    $settings->rate = -1.5;
    $assert('negative rate returns 0.0 duty', $calculator->calculate($cart, 'DE'), 0.0);

    // Check 2: Intrinsic value using get_cart_contents_total() (after coupons)
    // Subtotal is 180 EUR (above 150 threshold), but cart_contents_total is 140 EUR (below threshold).
    $settings->rate = 1.0;
    $cartWithDiscount = new MockCart(subtotal: 180.0, cartContentsTotal: 140.0);
    $assert('cart with subtotal > 150 but discounted total <= 150 qualifies for duty', $calculator->calculate($cartWithDiscount, 'DE'), 10.0);

    // Cart with discounted total > 150 does not qualify
    $cartAboveThreshold = new MockCart(subtotal: 200.0, cartContentsTotal: 160.0);
    $assert('cart with discounted total > 150 returns 0.0 duty', $calculator->calculate($cartAboveThreshold, 'DE'), 0.0);

    // Check 3: Fallback to get_subtotal() when get_cart_contents_total() <= 0
    $cartBeforeCalculation = new MockCart(subtotal: 120.0, cartContentsTotal: 0.0);
    $assert('fallback to subtotal when cart contents total is 0 qualifies if subtotal <= threshold', $calculator->calculate($cartBeforeCalculation, 'DE'), 10.0);

    $cartBeforeCalcOver = new MockCart(subtotal: 160.0, cartContentsTotal: 0.0);
    $assert('fallback to subtotal when cart contents total is 0 exceeds threshold', $calculator->calculate($cartBeforeCalcOver, 'DE'), 0.0);

    // Check 4: Rate conversion (e.g. store currency GBP: 1 EUR = 0.85 GBP)
    // Goods value 100 GBP -> 100 / 0.85 = 117.65 EUR <= 150 EUR threshold.
    // Duty in store currency: 10 EUR * 1 line * 0.85 = 8.50 GBP.
    $settings->rate = 0.85;
    $cartGbp = new MockCart(subtotal: 100.0, cartContentsTotal: 100.0);
    $assert('rate conversion applies to threshold and store duty', $calculator->calculate($cartGbp, 'DE'), 8.50);

    // Goods value 140 GBP -> 140 / 0.85 = 164.71 EUR > 150 EUR threshold -> 0.0
    $cartGbpOver = new MockCart(subtotal: 140.0, cartContentsTotal: 140.0);
    $assert('goods value in GBP exceeding 150 EUR threshold returns 0.0', $calculator->calculate($cartGbpOver, 'DE'), 0.0);

    // Check 5: Filter customs/intrinsic_goods_value
    // Filter reduces 160.0 down to 140.0, bringing it below threshold.
    $settings->rate = 1.0;
    \add_filter('customs/intrinsic_goods_value', static function (float $val, \WC_Cart $c): float {
        return $val - 30.0;
    });
    $cartFiltered = new MockCart(subtotal: 160.0, cartContentsTotal: 160.0);
    $assert('customs/intrinsic_goods_value filter modifies goods value', $calculator->calculate($cartFiltered, 'DE'), 10.0);

    echo "all duty calculator checks passed\n";

    echo "\n--- legal period: 1 July 2026 until 1 July 2028 (Regulation (EU) 2026/382, Art. 2) ---\n";

    $GLOBALS['wp_filters'] = [];
    $cart = new MockCart(subtotal: 100.0, cartContentsTotal: 100.0);
    $at   = static fn (string $when): \Closure => static fn (): int => (new \DateTimeImmutable($when, new \DateTimeZone('Europe/Brussels')))->getTimestamp();
    $settings->enforceDates = true;

    $assert('no duty one second before 1 July 2026', (new DutyCalculator($settings, $eu, $counter, $at('2026-06-30 23:59:59')))->calculate($cart, 'DE'), 0.0);
    $assert('duty from 1 July 2026 00:00 Brussels', (new DutyCalculator($settings, $eu, $counter, $at('2026-07-01 00:00:00')))->calculate($cart, 'DE'), 10.0);
    $assert('duty on the last second of the period', (new DutyCalculator($settings, $eu, $counter, $at('2028-06-30 23:59:59')))->calculate($cart, 'DE'), 10.0);
    $assert('no duty from 1 July 2028', (new DutyCalculator($settings, $eu, $counter, $at('2028-07-01 00:00:00')))->calculate($cart, 'DE'), 0.0);

    $settings->enforceDates = false;
    $assert('override: duty after 1 July 2028 when the period check is off', (new DutyCalculator($settings, $eu, $counter, $at('2028-09-01 12:00:00')))->calculate($cart, 'DE'), 10.0);

    echo "all legal-period checks passed\n";

    echo "\n--- Ceuta and Melilla are outside the EU customs territory (Regulation (EU) No 952/2013, Art. 4(1)) ---\n";

    $assert('US to mainland Spain is charged', $calculator->calculate($cart, 'ES', '28001'), 10.0);
    $assert('US to Ceuta (51001) is not charged', $calculator->calculate($cart, 'ES', '51001'), 0.0);
    $assert('US to Melilla (52006) is not charged', $calculator->calculate($cart, 'ES', '52006'), 0.0);

    $settings->origin         = 'ES';
    $settings->originPostcode = '51001';
    $assert('Ceuta store to mainland Spain is charged', $calculator->calculate($cart, 'ES', '28001'), 10.0);
    $assert('Ceuta store to Germany is charged', $calculator->calculate($cart, 'DE'), 10.0);

    $settings->originPostcode = '28001';
    $assert('Madrid store to Germany is not charged', $calculator->calculate($cart, 'DE'), 0.0);

    echo "all Ceuta and Melilla checks passed\n";
}
