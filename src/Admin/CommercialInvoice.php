<?php

declare(strict_types=1);

namespace Customs\Admin;

use Customs\Contract\HasHooks;
use Customs\Order\OrderCustomsData;
use Customs\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Generates printable Commercial Invoice and Customs Declaration paperwork (CN22/CN23)
 * for WooCommerce orders.
 */
final class CommercialInvoice implements HasHooks
{
    private const ACTION_PRINT = 'customs_invoice';

    public function __construct(
        private readonly OrderCustomsData $orderData,
        private readonly SettingsRepository $settings,
    ) {
    }

    public function registerHooks(): void
    {
        add_action('add_meta_boxes', [$this, 'registerMetaBoxes'], 20, 2);
        add_action('woocommerce_admin_order_actions_end', [$this, 'renderOrderListAction']);
        add_action('admin_post_' . self::ACTION_PRINT, [$this, 'renderPrintableDocument']);
    }

    /**
     * Register customs paperwork metabox on both classic CPT and HPOS order screens.
     *
     * @param string $screenId
     * @param mixed  $postOrOrder
     */
    public function registerMetaBoxes($screenId, $postOrOrder): void
    {
        $screens = ['shop_order'];
        if (function_exists('wc_get_page_screen_id')) {
            $hposScreen = wc_get_page_screen_id('shop-order');
            if (is_string($hposScreen) && '' !== $hposScreen && ! in_array($hposScreen, $screens, true)) {
                $screens[] = $hposScreen;
            }
        }

        foreach ($screens as $screen) {
            add_meta_box(
                'customs_order_paperwork',
                __('Customs Paperwork', 'plogins-customs'),
                [$this, 'renderMetabox'],
                $screen,
                'side',
                'default'
            );
        }
    }

    /**
     * Render order edit sidebar metabox.
     *
     * @param mixed $postOrOrder
     */
    public function renderMetabox($postOrOrder): void
    {
        $order = $postOrOrder instanceof \WC_Order ? $postOrOrder : null;
        if (! $order && is_object($postOrOrder) && isset($postOrOrder->ID)) {
            $order = wc_get_order((int) $postOrOrder->ID);
        }

        if (! $order instanceof \WC_Order) {
            echo '<p>' . esc_html__('No order found.', 'plogins-customs') . '</p>';
            return;
        }

        $data = $this->orderData->forOrder($order);
        $orderId = (int) $data['order_id'];
        $printUrl = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION_PRINT . '&order_id=' . $orderId),
            'customs_invoice_' . $orderId
        );
        $csvUrl = wp_nonce_url(
            admin_url('admin-post.php?action=customs_order_csv&order_id=' . $orderId),
            'customs_order_csv_' . $orderId
        );

        $eori = $this->settings->eori();
        ?>
        <div class="customs-metabox-content" style="font-size:13px; line-height:1.5;">
            <p style="margin:0 0 8px 0;">
                <strong><?php echo esc_html__('Destination:', 'plogins-customs'); ?></strong>
                <?php echo esc_html($data['consignee']['country'] ?: '-'); ?>
                <?php if ($data['is_eu_dest']) : ?>
                    <span class="badge" style="background:#dcfce7; color:#15803d; padding:2px 6px; border-radius:3px; font-size:11px;"><?php echo esc_html__('EU Customs Territory', 'plogins-customs'); ?></span>
                <?php else : ?>
                    <span class="badge" style="background:#fef3c7; color:#b45309; padding:2px 6px; border-radius:3px; font-size:11px;"><?php echo esc_html__('Outside EU', 'plogins-customs'); ?></span>
                <?php endif; ?>
            </p>
            <p style="margin:0 0 8px 0;">
                <strong><?php echo esc_html__('Shippable Items:', 'plogins-customs'); ?></strong>
                <?php echo esc_html(sprintf(__('%d items (%s kg)', 'plogins-customs'), count($data['items']), (string) $data['total_weight_kg'])); ?>
            </p>
            <p style="margin:0 0 8px 0;">
                <strong><?php echo esc_html__('Store EORI:', 'plogins-customs'); ?></strong>
                <?php echo esc_html('' !== $eori ? $eori : __('Not configured in settings', 'plogins-customs')); ?>
            </p>
            <?php if (0.0 < (float) $data['duty']['amount']) : ?>
                <p style="margin:0 0 8px 0;">
                    <strong><?php echo esc_html__('EU Import Duty:', 'plogins-customs'); ?></strong>
                    <?php echo esc_html(sprintf('%s %s (%s)', (string) $data['duty']['amount'], $data['currency'], $data['duty']['mode'])); ?>
                </p>
            <?php endif; ?>
            <hr style="margin:12px 0; border:none; border-top:1px solid #e2e8f0;" />
            <p style="margin:0 0 8px 0;">
                <a href="<?php echo esc_url($printUrl); ?>" target="_blank" class="button button-primary" style="width:100%; text-align:center;">
                    <?php echo esc_html__('Print Customs Slip / CN22', 'plogins-customs'); ?>
                </a>
            </p>
            <p style="margin:0;">
                <a href="<?php echo esc_url($csvUrl); ?>" class="button button-secondary" style="width:100%; text-align:center;">
                    <?php echo esc_html__('Export Carrier CSV', 'plogins-customs'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Add action button in WooCommerce orders list table.
     *
     * @param mixed $order
     */
    public function renderOrderListAction($order): void
    {
        if (! $order instanceof \WC_Order) {
            return;
        }

        $orderId = $order->get_id();
        $printUrl = wp_nonce_url(
            admin_url('admin-post.php?action=' . self::ACTION_PRINT . '&order_id=' . $orderId),
            'customs_invoice_' . $orderId
        );

        printf(
            '<a class="button wc-action-button wc-action-button-customs-slip customs-slip-btn" href="%s" target="_blank" title="%s" style="margin-left:4px;">%s</a>',
            esc_url($printUrl),
            esc_attr__('Print Customs Slip / Commercial Invoice', 'plogins-customs'),
            esc_html__('Customs', 'plogins-customs')
        );
    }

    /**
     * Render self-contained printable commercial invoice / customs declaration page.
     */
    public function renderPrintableDocument(): void
    {
        if (! current_user_can('edit_shop_orders') && ! current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to view customs documents.', 'plogins-customs'));
        }

        $orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
        $nonce   = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';

        if (! wp_verify_nonce($nonce, 'customs_invoice_' . $orderId)) {
            wp_die(esc_html__('Invalid or expired security token.', 'plogins-customs'));
        }

        $order = wc_get_order($orderId);
        if (! $order instanceof \WC_Order) {
            wp_die(esc_html__('Order not found.', 'plogins-customs'));
        }

        $data = $this->orderData->forOrder($order);

        $sender    = $data['sender'];
        $consignee = $data['consignee'];
        $currency  = $data['currency'];
        $items     = $data['items'];

        ?>
        <!DOCTYPE html>
        <html lang="<?php echo esc_attr(get_locale()); ?>">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html(sprintf(__('Customs Declaration - Order #%s', 'plogins-customs'), (string) $data['order_number'])); ?></title>
            <style>
                *, *::before, *::after { box-sizing: border-box; }
                body {
                    margin: 0;
                    padding: 24px;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    font-size: 13px;
                    line-height: 1.45;
                    color: #1e293b;
                    background-color: #f8fafc;
                }
                .container {
                    max-width: 800px;
                    margin: 0 auto;
                    background: #ffffff;
                    padding: 32px;
                    border: 1px solid #cbd5e1;
                    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
                }
                .action-bar {
                    max-width: 800px;
                    margin: 0 auto 16px auto;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .btn {
                    display: inline-block;
                    padding: 8px 16px;
                    font-weight: 600;
                    font-size: 13px;
                    text-decoration: none;
                    border-radius: 4px;
                    cursor: pointer;
                    border: 1px solid transparent;
                }
                .btn-primary { background: #2563eb; color: #ffffff; }
                .btn-secondary { background: #e2e8f0; color: #334155; }
                .header-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    border-bottom: 2px solid #0f172a;
                    padding-bottom: 16px;
                    margin-bottom: 20px;
                }
                .title-block h1 {
                    margin: 0 0 4px 0;
                    font-size: 20px;
                    letter-spacing: -0.02em;
                    color: #0f172a;
                    text-transform: uppercase;
                }
                .title-block .subtitle {
                    font-size: 11px;
                    color: #64748b;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                }
                .meta-block { text-align: right; font-size: 12px; }
                .parties-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 24px;
                    margin-bottom: 24px;
                }
                .party-box {
                    border: 1px solid #e2e8f0;
                    padding: 14px;
                    border-radius: 4px;
                    background: #f8fafc;
                }
                .party-box h2 {
                    margin: 0 0 8px 0;
                    font-size: 12px;
                    text-transform: uppercase;
                    color: #475569;
                    letter-spacing: 0.05em;
                    border-bottom: 1px solid #cbd5e1;
                    padding-bottom: 4px;
                }
                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                    font-size: 12px;
                }
                .items-table th {
                    background: #f1f5f9;
                    color: #334155;
                    text-align: left;
                    padding: 8px 10px;
                    border-bottom: 1px solid #cbd5e1;
                    font-weight: 600;
                    text-transform: uppercase;
                    font-size: 11px;
                }
                .items-table td {
                    padding: 8px 10px;
                    border-bottom: 1px solid #e2e8f0;
                    vertical-align: top;
                }
                .items-table .num { text-align: right; }
                .totals-grid {
                    display: grid;
                    grid-template-columns: 1fr 280px;
                    gap: 24px;
                    margin-bottom: 24px;
                }
                .summary-table { width: 100%; border-collapse: collapse; font-size: 12px; }
                .summary-table td { padding: 4px 8px; }
                .summary-table td.num { text-align: right; font-weight: 600; }
                .declaration-box {
                    border: 1px dashed #94a3b8;
                    padding: 12px;
                    border-radius: 4px;
                    font-size: 11px;
                    color: #475569;
                    margin-top: 16px;
                }
                .signature-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 24px;
                    margin-top: 24px;
                    font-size: 12px;
                }
                .sign-line {
                    border-bottom: 1px solid #94a3b8;
                    height: 36px;
                    margin-bottom: 4px;
                }
                @media print {
                    body { background: #ffffff; padding: 0; font-size: 11pt; color: #000000; }
                    .action-bar { display: none !important; }
                    .container {
                        max-width: 100%;
                        border: none;
                        box-shadow: none;
                        padding: 0;
                    }
                    .party-box { background: transparent; }
                    .items-table th { background: #e2e8f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                }
            </style>
        </head>
        <body>
            <div class="action-bar">
                <div>
                    <button type="button" class="btn btn-primary" onclick="window.print();"><?php echo esc_html__('Print Document', 'plogins-customs'); ?></button>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary" onclick="window.close();"><?php echo esc_html__('Close', 'plogins-customs'); ?></button>
                </div>
            </div>

            <div class="container">
                <div class="header-row">
                    <div class="title-block">
                        <h1><?php echo esc_html__('Commercial Invoice / Customs Declaration', 'plogins-customs'); ?></h1>
                        <div class="subtitle"><?php echo esc_html__('Council Regulation (EU) 2026/382 - CN22 / CN23 Declarations', 'plogins-customs'); ?></div>
                    </div>
                    <div class="meta-block">
                        <div><strong><?php echo esc_html__('Order #:', 'plogins-customs'); ?></strong> <?php echo esc_html((string) $data['order_number']); ?></div>
                        <div><strong><?php echo esc_html__('Date:', 'plogins-customs'); ?></strong> <?php echo esc_html($data['order_date'] ?: date_i18n('Y-m-d')); ?></div>
                        <div><strong><?php echo esc_html__('Currency:', 'plogins-customs'); ?></strong> <?php echo esc_html($currency); ?></div>
                    </div>
                </div>

                <div class="parties-grid">
                    <div class="party-box">
                        <h2><?php echo esc_html__('Shipper / Exporter', 'plogins-customs'); ?></h2>
                        <strong><?php echo esc_html($sender['name']); ?></strong><br />
                        <?php if ('' !== $sender['address1']) : ?><?php echo esc_html($sender['address1']); ?><br /><?php endif; ?>
                        <?php if ('' !== $sender['address2']) : ?><?php echo esc_html($sender['address2']); ?><br /><?php endif; ?>
                        <?php echo esc_html(trim($sender['postcode'] . ' ' . $sender['city'])); ?><br />
                        <?php if ('' !== $sender['state']) : ?><?php echo esc_html($sender['state'] . ', '); ?><?php endif; ?>
                        <strong><?php echo esc_html($sender['country']); ?></strong><br />
                        <?php if ('' !== $sender['eori']) : ?>
                            <div style="margin-top:6px; font-weight:600;">
                                <?php echo esc_html(sprintf(__('EORI: %s', 'plogins-customs'), $sender['eori'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="party-box">
                        <h2><?php echo esc_html__('Consignee / Importer', 'plogins-customs'); ?></h2>
                        <strong><?php echo esc_html($consignee['name']); ?></strong><br />
                        <?php if ('' !== $consignee['company']) : ?><?php echo esc_html($consignee['company']); ?><br /><?php endif; ?>
                        <?php if ('' !== $consignee['address1']) : ?><?php echo esc_html($consignee['address1']); ?><br /><?php endif; ?>
                        <?php if ('' !== $consignee['address2']) : ?><?php echo esc_html($consignee['address2']); ?><br /><?php endif; ?>
                        <?php echo esc_html(trim($consignee['postcode'] . ' ' . $consignee['city'])); ?><br />
                        <?php if ('' !== $consignee['state']) : ?><?php echo esc_html($consignee['state'] . ', '); ?><?php endif; ?>
                        <strong><?php echo esc_html($consignee['country']); ?></strong><br />
                        <?php if ('' !== $consignee['phone']) : ?><div><?php echo esc_html(sprintf(__('Tel: %s', 'plogins-customs'), $consignee['phone'])); ?></div><?php endif; ?>
                        <?php if ('' !== $consignee['email']) : ?><div><?php echo esc_html(sprintf(__('Email: %s', 'plogins-customs'), $consignee['email'])); ?></div><?php endif; ?>
                    </div>
                </div>

                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:24px;">#</th>
                            <th><?php echo esc_html__('Description of Goods', 'plogins-customs'); ?></th>
                            <th><?php echo esc_html__('HS Code', 'plogins-customs'); ?></th>
                            <th><?php echo esc_html__('Origin', 'plogins-customs'); ?></th>
                            <th class="num"><?php echo esc_html__('Qty', 'plogins-customs'); ?></th>
                            <th class="num"><?php echo esc_html__('Net Wt (kg)', 'plogins-customs'); ?></th>
                            <th class="num"><?php echo esc_html__('Unit Val', 'plogins-customs'); ?></th>
                            <th class="num"><?php echo esc_html__('Total Val', 'plogins-customs'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx = 1; foreach ($items as $item) : ?>
                            <tr>
                                <td><?php echo (int) $idx++; ?></td>
                                <td>
                                    <strong><?php echo esc_html($item['customs_desc']); ?></strong>
                                    <?php if ($item['customs_desc'] !== $item['name']) : ?>
                                        <br /><small style="color:#64748b;"><?php echo esc_html($item['name']); ?></small>
                                    <?php endif; ?>
                                    <?php if ('' !== $item['sku']) : ?>
                                        <br /><small style="color:#64748b;"><?php echo esc_html(sprintf(__('SKU: %s', 'plogins-customs'), $item['sku'])); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($item['hs_code'] ?: '-'); ?></td>
                                <td><?php echo esc_html($item['origin_country'] ?: '-'); ?></td>
                                <td class="num"><?php echo (int) $item['quantity']; ?></td>
                                <td class="num"><?php echo esc_html(number_format((float) $item['total_weight_kg'], 3, '.', '')); ?></td>
                                <td class="num"><?php echo esc_html(number_format((float) $item['unit_value'], 2, '.', '')); ?></td>
                                <td class="num"><?php echo esc_html(number_format((float) $item['total_value'], 2, '.', '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)) : ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:16px; color:#64748b;">
                                    <?php echo esc_html__('No physical shippable items found in this order.', 'plogins-customs'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="totals-grid">
                    <div>
                        <div class="declaration-box">
                            <strong><?php echo esc_html__('Declaration:', 'plogins-customs'); ?></strong>
                            <?php echo esc_html__('I, the undersigned, certify that the particulars given in this customs declaration are true and correct and that this consignment does not contain any dangerous articles or articles prohibited by legislation or by postal or customs regulations.', 'plogins-customs'); ?>
                        </div>
                    </div>
                    <div>
                        <table class="summary-table">
                            <tr>
                                <td><?php echo esc_html__('Total Items:', 'plogins-customs'); ?></td>
                                <td class="num"><?php echo (int) $data['total_items_qty']; ?></td>
                            </tr>
                            <tr>
                                <td><?php echo esc_html__('Total Net Weight:', 'plogins-customs'); ?></td>
                                <td class="num"><?php echo esc_html(number_format((float) $data['total_weight_kg'], 3, '.', '') . ' kg'); ?></td>
                            </tr>
                            <tr>
                                <td><?php echo esc_html__('Goods Subtotal:', 'plogins-customs'); ?></td>
                                <td class="num"><?php echo esc_html(number_format((float) $data['total_goods_val'], 2, '.', '') . ' ' . $currency); ?></td>
                            </tr>
                            <?php if (0.0 < (float) $data['duty']['amount']) : ?>
                                <tr>
                                    <td><?php echo esc_html(sprintf(__('EU Import Duty (%s):', 'plogins-customs'), $data['duty']['mode'])); ?></td>
                                    <td class="num"><?php echo esc_html(number_format((float) $data['duty']['amount'], 2, '.', '') . ' ' . $currency); ?></td>
                                </tr>
                            <?php endif; ?>
                            <tr style="border-top:1px solid #cbd5e1;">
                                <td><strong><?php echo esc_html__('Declared Total:', 'plogins-customs'); ?></strong></td>
                                <td class="num"><strong><?php echo esc_html(number_format((float) $data['order_total'], 2, '.', '') . ' ' . $currency); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="signature-grid">
                    <div>
                        <div class="sign-line"></div>
                        <div><?php echo esc_html__('Date and Place', 'plogins-customs'); ?></div>
                    </div>
                    <div>
                        <div class="sign-line"></div>
                        <div><?php echo esc_html__('Authorized Signature', 'plogins-customs'); ?></div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
