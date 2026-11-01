<?php

declare(strict_types=1);

namespace Customs\Admin;

use Customs\Contract\HasHooks;
use Customs\Order\OrderCustomsData;
use Customs\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Handles single-order and bulk carrier CSV export for customs dispatch and parcel manifests.
 *
 * Implements spreadsheet formula injection defense on all user and store inputs.
 */
final class CarrierCsvExport implements HasHooks
{
    private const ACTION_SINGLE = 'customs_order_csv';
    private const ACTION_BULK   = 'customs_bulk_csv';

    public function __construct(
        private readonly OrderCustomsData $orderData,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('admin_post_' . self::ACTION_SINGLE, [$this, 'exportSingleOrder']);

        // Bulk action on classic orders list and HPOS orders list.
        add_filter('bulk_actions-edit-shop_order', [$this, 'registerBulkAction']);
        add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'registerBulkAction']);

        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handleBulkAction'], 10, 3);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handleBulkAction'], 10, 3);
    }

    /**
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function registerBulkAction(array $actions): array
    {
        $actions[self::ACTION_BULK] = __('Export Carrier Customs CSV', 'plogins-customs');

        return $actions;
    }

    /**
     * @param string $redirectUrl
     * @param string $action
     * @param array<int|string> $ids
     * @return string
     */
    public function handleBulkAction(string $redirectUrl, string $action, array $ids): string
    {
        if (self::ACTION_BULK !== $action || empty($ids)) {
            return $redirectUrl;
        }

        if (! current_user_can('edit_shop_orders') && ! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to export orders.', 'plogins-customs'));
        }

        $orderIds = array_map('intval', $ids);
        $filename = 'customs-orders-bulk-' . gmdate('Y-m-d-His') . '.csv';

        $this->outputCsvHeaders($filename);
        $this->streamOrdersCsv($orderIds);
        exit;
    }

    /**
     * Export single order customs lines.
     */
    public function exportSingleOrder(): void
    {
        if (! current_user_can('edit_shop_orders') && ! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to export customs data.', 'plogins-customs'));
        }

        $orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
        $nonce   = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (! wp_verify_nonce($nonce, 'customs_order_csv_' . $orderId)) {
            wp_die(esc_html__('Invalid or expired security token.', 'plogins-customs'));
        }

        $filename = 'customs-order-' . $orderId . '-' . gmdate('Y-m-d') . '.csv';
        $this->outputCsvHeaders($filename);
        $this->streamOrdersCsv([$orderId]);
        exit;
    }

    /**
     * @param array<int> $orderIds
     */
    private function streamOrdersCsv(array $orderIds): void
    {
        $out = fopen('php://output', 'wb');
        if (false === $out) {
            return;
        }

        // UTF-8 BOM for Microsoft Excel compatibility.
        fwrite($out, "\xEF\xBB\xBF");

        $headers = [
            'Order_ID',
            'Order_Number',
            'Order_Date',
            'Recipient_Name',
            'Recipient_Company',
            'Recipient_Address1',
            'Recipient_Address2',
            'Recipient_City',
            'Recipient_State',
            'Recipient_Postcode',
            'Recipient_Country',
            'Recipient_Phone',
            'Recipient_Email',
            'Item_SKU',
            'Item_Name',
            'Customs_Description',
            'HS_Code',
            'Origin_Country',
            'Quantity',
            'Unit_Weight_KG',
            'Total_Weight_KG',
            'Unit_Value',
            'Total_Value',
            'Currency',
            'Sender_EORI',
            'Duty_Mode',
            'Duty_Amount',
        ];

        fputcsv($out, array_map([self::class, 'escapeCell'], $headers));

        foreach ($orderIds as $id) {
            $order = wc_get_order($id);
            if (! $order instanceof \WC_Order) {
                continue;
            }

            $data      = $this->orderData->forOrder($order);
            $consignee = $data['consignee'];
            $sender    = $data['sender'];
            $duty      = $data['duty'];

            foreach ($data['items'] as $item) {
                $row = [
                    (string) $data['order_id'],
                    (string) $data['order_number'],
                    (string) $data['order_date'],
                    (string) $consignee['name'],
                    (string) $consignee['company'],
                    (string) $consignee['address1'],
                    (string) $consignee['address2'],
                    (string) $consignee['city'],
                    (string) $consignee['state'],
                    (string) $consignee['postcode'],
                    (string) $consignee['country'],
                    (string) $consignee['phone'],
                    (string) $consignee['email'],
                    (string) $item['sku'],
                    (string) $item['name'],
                    (string) $item['customs_desc'],
                    (string) $item['hs_code'],
                    (string) $item['origin_country'],
                    (string) $item['quantity'],
                    number_format((float) $item['unit_weight_kg'], 4, '.', ''),
                    number_format((float) $item['total_weight_kg'], 4, '.', ''),
                    number_format((float) $item['unit_value'], 4, '.', ''),
                    number_format((float) $item['total_value'], 2, '.', ''),
                    (string) $data['currency'],
                    (string) $sender['eori'],
                    (string) $duty['mode'],
                    number_format((float) $duty['amount'], 2, '.', ''),
                ];

                fputcsv($out, array_map([self::class, 'escapeCell'], $row));
            }
        }

        fclose($out);
    }

    /**
     * Send standard CSV attachment headers.
     */
    private function outputCsvHeaders(string $filename): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * Defends against CSV Formula Injection (CWE-1236).
     *
     * Prefixes sensitive leading spreadsheet characters with a single quote.
     */
    public static function escapeCell(string $cell): string
    {
        if ('' !== $cell && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $cell;
        }

        return $cell;
    }
}
