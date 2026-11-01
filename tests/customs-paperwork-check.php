<?php
/**
 * Self-check for Customs Paperwork (EORI, Customs Descriptions, Carrier CSV formula defense, Order Data).
 *
 * Run: php tests/customs-paperwork-check.php
 *
 * @package plogins-customs
 */

namespace {
    define('ABSPATH', __DIR__);
    define('CUSTOMS_DIR', __DIR__ . '/../');

    $GLOBALS['settings'] = [
        'eori'           => 'GB123456789000',
        'origin_country' => 'US',
        'goods_origin'   => 'CN',
        'duty_mode'      => 'charge',
    ];

    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return $text;
    }
    function get_bloginfo(string $show = ''): string
    {
        return 'Test Global Store';
    }
    function get_option(string $name, $default = false)
    {
        if ('customs_settings' === $name) {
            return $GLOBALS['settings'];
        }
        if ('woocommerce_store_address' === $name) {
            return '123 Harbor Blvd';
        }
        if ('woocommerce_store_city' === $name) {
            return 'New York';
        }
        return $default;
    }
    function wc_get_base_location(): array
    {
        return ['country' => 'US', 'state' => 'NY'];
    }
    function wc_get_weight(float $weight, string $unit): float
    {
        return $weight; // Assume kg in tests
    }
    function apply_filters(string $hook, $value, ...$args)
    {
        return $value;
    }

    class WC_DateTime extends \DateTime
    {
    }

    class WC_Product
    {
        public function __construct(
            public array $meta = [],
            public bool $virtual = false,
            public float $weight = 0.5,
            public string $sku = 'TEST-SKU-1'
        ) {}

        public function is_virtual(): bool
        {
            return $this->virtual;
        }

        public function has_weight(): bool
        {
            return $this->weight > 0;
        }

        public function get_weight(): float
        {
            return $this->weight;
        }

        public function get_sku(): string
        {
            return $this->sku;
        }

        public function get_meta(string $key)
        {
            return $this->meta[$key] ?? '';
        }

        public function get_parent_id(): int
        {
            return 0;
        }
    }

    class WC_Order_Item_Product
    {
        public function __construct(
            public int $id,
            public string $name,
            public int $qty,
            public float $total,
            public WC_Product $product
        ) {}

        public function get_id(): int
        {
            return $this->id;
        }

        public function get_name(): string
        {
            return $this->name;
        }

        public function get_quantity(): int
        {
            return $this->qty;
        }

        public function get_total(): float
        {
            return $this->total;
        }

        public function get_product(): WC_Product
        {
            return $this->product;
        }

        public function get_product_id(): int
        {
            return 101;
        }

        public function get_variation_id(): int
        {
            return 0;
        }
    }

    class WC_Order_Item_Fee
    {
        public function __construct(public string $name, public float $total) {}

        public function get_name(): string
        {
            return $this->name;
        }

        public function get_total(): float
        {
            return $this->total;
        }
    }

    class WC_Order
    {
        public int $id = 42;
        public array $items = [];
        public array $fees = [];
        public array $meta = [];

        public function get_id(): int
        {
            return $this->id;
        }

        public function get_order_number(): string
        {
            return '42';
        }

        public function get_date_created(): ?WC_DateTime
        {
            return new WC_DateTime('2026-11-01 12:00:00');
        }

        public function get_currency(): string
        {
            return 'USD';
        }

        public function get_shipping_country(): string
        {
            return 'DE';
        }

        public function get_shipping_first_name(): string
        {
            return 'Hans';
        }

        public function get_shipping_last_name(): string
        {
            return 'Schmidt';
        }

        public function get_shipping_company(): string
        {
            return 'Schmidt GmbH';
        }

        public function get_shipping_address_1(): string
        {
            return 'Musterstrasse 1';
        }

        public function get_shipping_address_2(): string
        {
            return '';
        }

        public function get_shipping_city(): string
        {
            return 'Berlin';
        }

        public function get_shipping_state(): string
        {
            return 'BE';
        }

        public function get_shipping_postcode(): string
        {
            return '10115';
        }

        public function get_billing_first_name(): string
        {
            return 'Hans';
        }

        public function get_billing_last_name(): string
        {
            return 'Schmidt';
        }

        public function get_billing_country(): string
        {
            return 'DE';
        }

        public function get_billing_phone(): string
        {
            return '+49123456789';
        }

        public function get_billing_email(): string
        {
            return 'hans@example.de';
        }

        public function get_items(): array
        {
            return $this->items;
        }

        public function get_fees(): array
        {
            return $this->fees;
        }

        public function get_meta(string $key)
        {
            return $this->meta[$key] ?? '';
        }

        public function get_total(): float
        {
            return 95.0;
        }
    }
}

namespace {
    require __DIR__ . '/../src/Contract/HasHooks.php';
    require __DIR__ . '/../src/Service/Texts.php';
    require __DIR__ . '/../src/Settings/SettingsRepository.php';
    require __DIR__ . '/../src/Geo/EuMembership.php';
    require __DIR__ . '/../src/Duty/TariffLineCounter.php';
    require __DIR__ . '/../src/Cart/DutyFeeApplicator.php';
    require __DIR__ . '/../src/Order/OrderCustomsData.php';
    require __DIR__ . '/../src/Admin/CarrierCsvExport.php';

    use Customs\Admin\CarrierCsvExport;
    use Customs\Cart\DutyFeeApplicator;
    use Customs\Duty\TariffLineCounter;
    use Customs\Geo\EuMembership;
    use Customs\Order\OrderCustomsData;
    use Customs\Settings\SettingsRepository;

    $check = static function (string $label, bool $ok): void {
        if (! $ok) {
            fwrite(STDERR, "FAIL {$label}\n");
            exit(1);
        }
        echo "ok   {$label}\n";
    };

    // 1. EORI sanitization tests
    $check('EORI: clean uppercase', SettingsRepository::sanitizeEori('gb123456789000') === 'GB123456789000');
    $check('EORI: strip whitespace and hyphens', SettingsRepository::sanitizeEori(' PL 123-456-78-90 ') === 'PL1234567890');
    $check('EORI: max 17 characters length restriction', strlen(SettingsRepository::sanitizeEori('GB12345678901234567890')) === 17);
    $check('EORI: empty string stays empty', SettingsRepository::sanitizeEori('') === '');

    // 2. CSV Formula Injection Defense tests
    $check('CSV: escape = formula', CarrierCsvExport::escapeCell('=SUM(A1:A10)') === "'=SUM(A1:A10)");
    $check('CSV: escape + formula', CarrierCsvExport::escapeCell('+cmd|/c') === "'+cmd|/c");
    $check('CSV: escape - formula', CarrierCsvExport::escapeCell('-10+20') === "'-10+20");
    $check('CSV: escape @ formula', CarrierCsvExport::escapeCell('@IMPORT') === "'@IMPORT");
    $check('CSV: escape leading tab', CarrierCsvExport::escapeCell("\tTAB") === "'\tTAB");
    $check('CSV: normal text unescaped', CarrierCsvExport::escapeCell('Cotton T-Shirt') === 'Cotton T-Shirt');
    $check('CSV: empty text untouched', CarrierCsvExport::escapeCell('') === '');

    // 3. OrderCustomsData extraction
    $settingsRepo = new SettingsRepository();
    $eu           = new EuMembership();
    $extractor    = new OrderCustomsData($settingsRepo, $eu);

    $order = new WC_Order();

    // Shipped physical product with custom customs description and HS code
    $product1 = new WC_Product(
        meta: [
            TariffLineCounter::DESC_META_KEY => 'Men knitted cotton t-shirt',
            TariffLineCounter::META_KEY      => '6109.10',
            TariffLineCounter::ORIGIN_META_KEY => 'BD',
        ],
        virtual: false,
        weight: 0.25,
        sku: 'TSHIRT-BLK-L'
    );
    $item1 = new WC_Order_Item_Product(1, 'Graphic T-Shirt - Black L', 2, 40.0, $product1);

    // Physical product without customs desc (should fallback to product title) and without origin (should fallback to CN)
    $product2 = new WC_Product(
        meta: [
            TariffLineCounter::META_KEY => '4202.11',
        ],
        virtual: false,
        weight: 0.8,
        sku: 'BAG-LTH-01'
    );
    $item2 = new WC_Order_Item_Product(2, 'Leather Messenger Bag', 1, 50.0, $product2);

    // Virtual product (downloadable ebook) - must be omitted from physical parcel declaration
    $product3 = new WC_Product(
        meta: [],
        virtual: true,
        weight: 0.0,
        sku: 'EBOOK-01'
    );
    $item3 = new WC_Order_Item_Product(3, 'Digital User Manual PDF', 1, 5.0, $product3);

    $order->items = [$item1, $item2, $item3];
    $order->fees  = [new WC_Order_Item_Fee($settingsRepo->label(), 6.0)];

    $data = $extractor->forOrder($order);

    $check('Order data: ID matches', $data['order_id'] === 42);
    $check('Order data: destination recognized as EU', $data['is_eu_dest'] === true);
    $check('Order data: sender EORI captured', $data['sender']['eori'] === 'GB123456789000');
    $check('Order data: consignee country matches', $data['consignee']['country'] === 'DE');

    // Items checks
    $check('Order data: virtual items excluded (2 physical items remain)', count($data['items']) === 2);

    $line1 = $data['items'][0];
    $check('Item 1: custom customs description used', $line1['customs_desc'] === 'Men knitted cotton t-shirt');
    $check('Item 1: HS code captured', $line1['hs_code'] === '6109.10');
    $check('Item 1: specific origin BD used', $line1['origin_country'] === 'BD');
    $check('Item 1: quantity is 2', $line1['quantity'] === 2);
    $check('Item 1: total weight is 0.5 kg (2 * 0.25)', (float) $line1['total_weight_kg'] === 0.5);

    $line2 = $data['items'][1];
    $check('Item 2: fallback to item title when customs desc missing', $line2['customs_desc'] === 'Leather Messenger Bag');
    $check('Item 2: fallback to store goods_origin CN', $line2['origin_country'] === 'CN');

    $check('Totals: total quantity is 3 (2 shirts + 1 bag)', $data['total_items_qty'] === 3);
    $check('Totals: total weight is 1.3 kg (0.5 + 0.8)', (float) $data['total_weight_kg'] === 1.3);
    $check('Duty: detected charged fee', (float) $data['duty']['amount'] === 6.0 && $data['duty']['mode'] === 'charge');

    echo "all customs paperwork checks passed\n";
}
