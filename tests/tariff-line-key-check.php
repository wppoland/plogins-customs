<?php
/**
 * Self-check for tariff-line grouping. Run: php tests/tariff-line-key-check.php
 *
 * Covers the case a merchant reported: books split across Books > Health and
 * Books > Self improvement were counted as two tariff lines and charged twice,
 * and a shared tariff code was expected to collapse them into one.
 *
 * No framework on purpose. WordPress and WooCommerce are stubbed down to the
 * three functions and two shapes this class actually touches.
 *
 * @package plogins-customs
 */

define('ABSPATH', __DIR__);
define('CUSTOMS_DIR', __DIR__ . '/../');

// term id => parent id. 10 Books, 11 Health, 12 Self improvement, 20 Gifts.
const TERM_PARENTS = [10 => 0, 11 => 10, 12 => 10, 20 => 0, 21 => 20];

function get_ancestors(int $id, string $taxonomy, string $type = ''): array
{
    $out = [];
    while ((TERM_PARENTS[$id] ?? 0) > 0) {
        $id    = TERM_PARENTS[$id];
        $out[] = $id;
    }

    return $out;
}

function __(string $text, string $domain = 'default'): string
{
    return $text;
}

function apply_filters(string $hook, $value, ...$args)
{
    return $value;
}

function wc_get_product($id)
{
    global $product_stubs;
    foreach ($product_stubs ?? [] as $p) {
        if ($p->get_id() === (int) $id) {
            return $p;
        }
    }

    return null;
}

$GLOBALS['product_meta'] = [];

function get_post_meta($post_id, $key = '', $single = false)
{
    $pid = (int) $post_id;
    $all = $GLOBALS['product_meta'][$pid] ?? [];
    if ('' === $key) {
        return $all;
    }
    if (! isset($all[$key])) {
        return $single ? '' : [];
    }
    $values = $all[$key];
    if ($single) {
        return is_array($values) ? reset($values) : $values;
    }

    return is_array($values) ? $values : [$values];
}

$GLOBALS['group_subcategories'] = false;

function get_option(string $name, $default = false)
{
    // SettingsRepository is final, so the settings are fed through the option
    // it actually reads rather than through a subclass.
    return [
        'count_basis'         => 'category',
        'group_subcategories' => $GLOBALS['group_subcategories'],
    ];
}

abstract class WC_Product_Stub
{
    /** @param list<int> $categories */
    public function __construct(
        private int $id,
        private array $categories = [],
        private string $code = '',
        private int $parent = 0,
    ) {
        global $product_stubs;
        if (! isset($product_stubs)) {
            $product_stubs = [];
        }
        $product_stubs[$this->id] = $this;

        if ('' !== $this->code) {
            $GLOBALS['product_meta'][$this->id] = $GLOBALS['product_meta'][$this->id] ?? [];
            $GLOBALS['product_meta'][$this->id]['_customs_tariff_code'] = $this->code;
        }
    }

    public function get_id(): int
    {
        return $this->id;
    }

    public function get_parent_id(): int
    {
        return $this->parent;
    }

    /** @return list<int> */
    public function get_category_ids(): array
    {
        return $this->categories;
    }

    public function get_meta(string $key, bool $single = false): string
    {
        return $this->code;
    }
}

class_alias('WC_Product_Stub', 'WC_Product_Base');
eval('class WC_Product extends WC_Product_Base {}');

class WC_Cart
{
    /** @param list<WC_Product> $products */
    public function __construct(private array $products)
    {
    }

    public function get_cart(): array
    {
        return array_map(static fn ($p) => ['data' => $p], $this->products);
    }
}

require __DIR__ . '/../src/Service/Texts.php';
require __DIR__ . '/../src/Settings/SettingsRepository.php';
require __DIR__ . '/../src/Duty/TariffLineCounter.php';

use Customs\Duty\TariffLineCounter;
use Customs\Settings\SettingsRepository;

$counter = new TariffLineCounter(new SettingsRepository());

$assert = static function (string $label, int $got, int $want): void {
    if ($got !== $want) {
        fwrite(STDERR, sprintf("FAIL %s: expected %d, got %d\n", $label, $want, $got));
        exit(1);
    }
    printf("ok   %s (%d)\n", $label, $got);
};

// Reported August 2026, one shop, two categories, opposite expectations.
// Off by default: sibling subcategories are separate lines.
$assert(
    'Other Products > Beads and > Pictures are two lines by default',
    $counter->count(new WC_Cart([
        new WC_Product(1, [11]),
        new WC_Product(2, [12]),
    ])),
    2,
);

// Turned on: they roll up to the category they sit in.
$GLOBALS['group_subcategories'] = true;
$assert(
    'Books > Health and > Self improvement are one line when grouping is on',
    $counter->count(new WC_Cart([
        new WC_Product(1, [11]),
        new WC_Product(2, [12]),
    ])),
    1,
);

// Grouping must not merge unrelated trees.
$assert(
    'different top-level categories stay separate while grouping is on',
    $counter->count(new WC_Cart([
        new WC_Product(1, [11]),
        new WC_Product(3, [21]),
    ])),
    2,
);

// And the answer must not depend on whether the parent was ticked as well.
$assert(
    'ticking the parent as well changes nothing',
    $counter->count(new WC_Cart([
        new WC_Product(1, [11]),
        new WC_Product(2, [10, 12]),
    ])),
    1,
);
$GLOBALS['group_subcategories'] = false;

// A tariff code beats the categories in either mode. This is the path that
// matters: it is the only one that can serve both shops at once.
$assert(
    'a shared tariff code collapses unrelated categories',
    $counter->count(new WC_Cart([
        new WC_Product(1, [11], '4901'),
        new WC_Product(3, [21], '4901'),
    ])),
    1,
);

$assert(
    'different tariff codes split one category',
    $counter->count(new WC_Cart([
        new WC_Product(1, [11], '7018'),
        new WC_Product(2, [11], '4911'),
    ])),
    2,
);

$GLOBALS['group_subcategories'] = true;
$assert(
    'different tariff codes split even while grouping is on',
    $counter->count(new WC_Cart([
        new WC_Product(1, [11], '7018'),
        new WC_Product(2, [12], '4911'),
    ])),
    2,
);
$GLOBALS['group_subcategories'] = false;

// Reported by the audit, not by a user, and it would have been: with grouping
// OFF a product carrying two categories must key on the one WooCommerce hands
// over first, exactly as 1.0.10 did. Keying on the lowest term id instead
// merges carts that used to count separately, and halves the duty, without any
// setting being touched.
$assert(
    'two multi-category products key on their first category, not the lowest id',
    $counter->count(new WC_Cart([
        new WC_Product(1, [11, 20]),
        new WC_Product(2, [20, 11]),
    ])),
    2,
);

echo "all tariff-line grouping checks passed\n";

echo "\n--- user-reported scenario: Books > Health + Books > Self improvement ---\n";

$scenarioA = $counter->count(new WC_Cart([
    new WC_Product(101, [11], '4901'),
    new WC_Product(102, [12], '4901'),
]));
$assert(
    'exact user scenario: Books > Health + Books > Self improvement with same HS code = 1 line',
    $scenarioA,
    1,
);

$scenarioB = $counter->count(new WC_Cart([
    new WC_Product(103, [11]),
    new WC_Product(104, [12]),
]));
$assert(
    'same books without HS code, grouping OFF = 2 lines (default behaviour)',
    $scenarioB,
    2,
);

$GLOBALS['group_subcategories'] = true;
$scenarioC = $counter->count(new WC_Cart([
    new WC_Product(105, [11]),
    new WC_Product(106, [12]),
]));
$assert(
    'same books without HS code, grouping ON = 1 line (workaround when codes not used)',
    $scenarioC,
    1,
);
$GLOBALS['group_subcategories'] = false;

echo "\n--- tariff code normalisation: formatting differences must not split lines ---\n";

$assert(
    '"4901.90" and "4901-90" are the same code',
    $counter->count(new WC_Cart([
        new WC_Product(201, [11], '4901.90'),
        new WC_Product(202, [12], '4901-90'),
    ])),
    1,
);

$assert(
    '" 4901 90 00 " and "4901.90.00" are the same code',
    $counter->count(new WC_Cart([
        new WC_Product(203, [11], ' 4901 90 00 '),
        new WC_Product(204, [12], '4901.90.00'),
    ])),
    1,
);

$assert(
    '"4901" and "4901.90" are still two different codes',
    $counter->count(new WC_Cart([
        new WC_Product(205, [11], '4901'),
        new WC_Product(206, [12], '4901.90'),
    ])),
    2,
);

$assert(
    'mixed-case codes compare case-insensitively',
    $counter->count(new WC_Cart([
        new WC_Product(207, [11], 't-4901'),
        new WC_Product(208, [12], 'T_4901'),
    ])),
    1,
);

echo "all user-scenario and normalisation checks passed\n";
