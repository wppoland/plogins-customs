<?php

declare(strict_types=1);

namespace Customs\Admin;

use Customs\Contract\HasHooks;
use Customs\Duty\TariffLineCounter;
use Customs\Geo\EuMembership;
use Customs\Service\Texts;
use Customs\Settings\SettingsRepository;

defined('ABSPATH') || exit;

/**
 * Settings screen under WooCommerce, for the EU import duty options.
 *
 * Saves are nonce-verified and capability-gated, the raw POST is run through
 * SettingsRepository::normalize() before storage, and every value is escaped on
 * output. The form posts back to itself rather than to options.php so the whole
 * settings array is stored under one option in its canonical shape.
 */
final class Settings implements HasHooks
{
    private const CAPABILITY = 'manage_woocommerce';
    private const PAGE_SLUG   = 'customs-settings';
    private const NONCE_ACTION = 'customs_save_settings';
    private const NONCE_FIELD  = 'customs_settings_nonce';

    /** Settings page hook suffix, captured so we can scope assets to it. */
    private string $hookSuffix = '';


    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly EuMembership $eu,
    ) {
    }


    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('plugin_action_links_' . plugin_basename(\Customs\PLUGIN_FILE), [$this, 'actionLinks']);
    }

    public function registerMenu(): void
    {
        $hook = add_submenu_page(
            'woocommerce',
            __('EU Import Duty', 'plogins-customs'),
            __('EU Import Duty', 'plogins-customs'),
            self::CAPABILITY,
            self::PAGE_SLUG,
            [$this, 'render']
        );

        $this->hookSuffix = is_string($hook) ? $hook : '';
    }

    /**
     * Load the admin stylesheet only on the Customs settings screen, never
     * across wp-admin.
     */
    public function enqueueAssets(string $hookSuffix): void
    {
        if ('' === $this->hookSuffix || $hookSuffix !== $this->hookSuffix) {
            return;
        }

        wp_enqueue_style(
            'customs-admin',
            CUSTOMS_URL . 'assets/css/admin.css',
            [],
            \Customs\VERSION
        );
    }

    /**
     * @param array<int, string> $links
     * @return array<int, string>
     */
    public function actionLinks($links): array
    {
        if (! is_array($links)) {
            $links = [];
        }

        $url = admin_url('admin.php?page=' . self::PAGE_SLUG);
        array_unshift(
            $links,
            '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'plogins-customs') . '</a>'
        );

        return $links;
    }

    public function render(): void
    {
        if (! current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to manage these settings.', 'plogins-customs'));
        }

        $saved = $this->maybeSave();
        // Raw, never the Texts-resolved values: a resolved label rendered as the
        // field value would be saved straight back and freeze one language into
        // the option. An empty field shows the translated default as a
        // placeholder instead.
        $s = $this->settings->rawSettings();

        ?>
        <div class="wrap customs-settings">
            <h1><?php echo esc_html__('EU Import Duty', 'plogins-customs'); ?></h1>

            <p class="description">
                <?php echo esc_html__('From 1 July 2026 the EU charges a flat customs duty per tariff line on parcels up to the goods-value threshold shipped into the EU from outside it. This estimate is shown as its own pre-tax line at cart and checkout.', 'plogins-customs'); ?>
            </p>

            <?php if ($saved) : ?>
                <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Settings saved.', 'plogins-customs'); ?></p></div>
            <?php endif; ?>

            <?php $this->renderOriginHint($s); ?>

            <form method="post" action="">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enable duty estimate', 'plogins-customs'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="1" <?php checked(! empty($s['enabled'])); ?> />
                                <?php echo esc_html__('Add the EU import duty line to qualifying carts.', 'plogins-customs'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="customs_per_line"><?php echo esc_html__('Duty per tariff line (EUR)', 'plogins-customs'); ?></label></th>
                        <td>
                            <input type="number" step="0.01" min="0" id="customs_per_line" name="per_line" value="<?php echo esc_attr((string) $s['per_line']); ?>" class="small-text" />
                            <p class="description"><?php echo esc_html__('EU rule: 3 EUR per distinct tariff line (temporary, until 1 July 2028).', 'plogins-customs'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="customs_threshold"><?php echo esc_html__('Goods-value threshold (EUR)', 'plogins-customs'); ?></label></th>
                        <td>
                            <input type="number" step="0.01" min="0" id="customs_threshold" name="threshold" value="<?php echo esc_attr((string) $s['threshold']); ?>" class="small-text" />
                            <p class="description"><?php echo esc_html__('The duty applies only when the cart goods value is at or below this amount. EU rule: 150 EUR.', 'plogins-customs'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="customs_eur_rate"><?php echo esc_html__('Store currency per 1 EUR', 'plogins-customs'); ?></label></th>
                        <td>
                            <input type="number" step="0.0001" min="0" id="customs_eur_rate" name="eur_rate" value="<?php echo esc_attr((string) $s['eur_rate']); ?>" class="small-text" />
                            <p class="description">
                                <?php
                                /* translators: %s: store currency code. */
                                echo esc_html(sprintf(__('Used to convert the EUR amounts into your store currency (%s). Leave at 1 if you sell in EUR.', 'plogins-customs'), get_woocommerce_currency()));
                                ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="customs_origin_country"><?php echo esc_html__('Store origin country', 'plogins-customs'); ?></label></th>
                        <td>
                            <select id="customs_origin_country" name="origin_country">
                                <option value=""><?php echo esc_html__('Use WooCommerce base country', 'plogins-customs'); ?></option>
                                <?php
                                $current = strtoupper((string) $s['origin_country']);
                                foreach ($this->countryList() as $code => $name) {
                                    printf(
                                        '<option value="%s" %s>%s</option>',
                                        esc_attr($code),
                                        selected($current, $code, false),
                                        esc_html($name)
                                    );
                                }
                                ?>
                            </select>
                            <p class="description"><?php echo esc_html__('Where parcels ship from. The duty only applies when this is outside the EU.', 'plogins-customs'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Tariff line basis', 'plogins-customs'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="count_basis" value="<?php echo esc_attr(SettingsRepository::BASIS_CATEGORY); ?>" <?php checked($s['count_basis'], SettingsRepository::BASIS_CATEGORY); ?> />
                                    <?php echo esc_html__('One line per distinct product category (recommended)', 'plogins-customs'); ?>
                                </label><br />
                                <label>
                                    <input type="radio" name="count_basis" value="<?php echo esc_attr(SettingsRepository::BASIS_PRODUCT); ?>" <?php checked($s['count_basis'], SettingsRepository::BASIS_PRODUCT); ?> />
                                    <?php echo esc_html__('One line per distinct product', 'plogins-customs'); ?>
                                </label>
                                <p class="description"><?php echo esc_html__('A tariff code set on a product always overrides this; products sharing a code count as one line.', 'plogins-customs'); ?></p>
                                <p style="margin-top:0.75em;">
                                    <label>
                                        <input type="checkbox" name="group_subcategories" value="1" <?php checked(! empty($s['group_subcategories'])); ?> />
                                        <?php echo esc_html__('Count a subcategory under the category it sits in', 'plogins-customs'); ?>
                                    </label>
                                </p>
                                <p class="description">
                                    <?php echo esc_html__('Off: Other Products > Beads and Other Products > Pictures count as two lines. On: Books > Health and Books > Self improvement count as one. Which one is right depends on what your categories mean, so it is a choice rather than a rule. Give the products a tariff code and neither setting applies to them.', 'plogins-customs'); ?>
                                </p>
                                <?php $this->renderTariffCodeCoverage(); ?>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="customs_label"><?php echo esc_html__('Checkout line label', 'plogins-customs'); ?></label></th>
                        <td>
                            <input type="text" id="customs_label" name="label" value="<?php echo esc_attr((string) $s['label']); ?>" placeholder="<?php echo esc_attr(Texts::defaults()['label']); ?>" class="regular-text" />
                            <p class="description"><?php echo esc_html__('Leave empty to use the translated default shown in the field, so the line reads in the language of each shop.', 'plogins-customs'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Apply tax to the duty', 'plogins-customs'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="taxable" value="1" <?php checked(! empty($s['taxable'])); ?> />
                                <?php echo esc_html__('Charge tax on the duty fee. Off by default, as customs duty is not normally taxed.', 'plogins-customs'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save settings', 'plogins-customs')); ?>
            </form>

            <?php
            /**
             * Fires below the settings form, inside the page wrapper, so an
             * add-on can render its own section in the same look.
             */
            do_action('customs/settings_after_form');
            ?>
        </div>
        <?php
    }

    /**
     * Verify, normalise and persist the submitted settings. Returns true when a
     * valid save was processed.
     *
     * @return bool
     */
    private function maybeSave(): bool
    {
        if ('POST' !== sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? ''))) {
            return false;
        }

        if (! current_user_can(self::CAPABILITY)) {
            return false;
        }

        $nonce = isset($_POST[self::NONCE_FIELD]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])) : '';
        if (! wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return false;
        }

        // Only the known keys are read; normalize() coerces every value to its
        // canonical type and ignores anything else in the POST.
        $raw = [
            'enabled'        => isset($_POST['enabled']),
            'per_line'       => isset($_POST['per_line']) ? sanitize_text_field(wp_unslash($_POST['per_line'])) : 0,
            'threshold'      => isset($_POST['threshold']) ? sanitize_text_field(wp_unslash($_POST['threshold'])) : 0,
            'eur_rate'       => isset($_POST['eur_rate']) ? sanitize_text_field(wp_unslash($_POST['eur_rate'])) : 1,
            'origin_country' => isset($_POST['origin_country']) ? sanitize_text_field(wp_unslash($_POST['origin_country'])) : '',
            'count_basis'    => isset($_POST['count_basis']) ? sanitize_text_field(wp_unslash($_POST['count_basis'])) : SettingsRepository::BASIS_CATEGORY,
            'label'          => isset($_POST['label']) ? sanitize_text_field(wp_unslash($_POST['label'])) : '',
            'taxable'        => isset($_POST['taxable']),
            // Every checkbox on this form has to be listed here or it can never
            // be saved: normalize() reads this array, not $_POST, so a key that
            // is missing reads as false and is written back over whatever was
            // stored. 1.0.12 shipped the subcategory checkbox without this line
            // and it was impossible to tick.
            'group_subcategories' => isset($_POST['group_subcategories']),
        ];

        update_option(SettingsRepository::OPTION, $this->settings->normalize($raw));

        return true;
    }

    /**
     * How many published products carry a tariff code the plugin can read.
     *
     * Worth showing, because "we entered the HS codes and nothing changed" is
     * indistinguishable from "the plugin cannot see them" without it: the field
     * lives on the Shipping tab, which WooCommerce hides for virtual products,
     * and a code typed into some other plugin's field is invisible here.
     */
    private function renderTariffCodeCoverage(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- one admin-screen count, not worth a transient
        $published = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
                'product',
                'publish',
            )
        );

        if ($published < 1) {
            return;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- as above
        $coded = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
                 WHERE p.post_type = %s AND p.post_status = %s
                   AND m.meta_key = %s AND TRIM(m.meta_value) <> ''",
                'product',
                'publish',
                TariffLineCounter::META_KEY,
            )
        );

        printf(
            '<p class="description" style="margin-top:0.75em;"><strong>%s</strong></p>',
            esc_html(
                sprintf(
                    /* translators: 1: products carrying a tariff code, 2: published products */
                    __('Tariff codes found on %1$d of %2$d published products.', 'plogins-customs'),
                    $coded,
                    $published,
                )
            )
        );

        if ($coded < $published) {
            printf(
                '<p class="description">%s</p>',
                esc_html__('The rest fall back to the category rule above. The field is called "Customs tariff code" and sits on the product\'s Shipping tab, which WooCommerce hides for products marked Virtual, and a code held in another plugin\'s field is not read here.', 'plogins-customs')
            );
        }
    }

    /**
     * Friendly notice about whether the resolved origin is inside the EU.
     *
     * @param array<string, mixed> $s
     */
    private function renderOriginHint(array $s): void
    {
        $origin = $this->settings->originCountry();
        if ('' === $origin) {
            echo '<div class="notice notice-warning inline"><p>';
            echo esc_html__('No store origin country is set in WooCommerce, so the duty cannot be applied. Set a base country or choose an origin below.', 'plogins-customs');
            echo '</p></div>';
            return;
        }

        if ($this->eu->isMember($origin)) {
            echo '<div class="notice notice-info inline"><p>';
            /* translators: %s: ISO country code. */
            echo esc_html(sprintf(__('Origin %s is inside the EU, so the duty will not be added (intra-EU shipments are excluded).', 'plogins-customs'), $origin));
            echo '</p></div>';
        }
    }

    /**
     * Country code => name list for the origin selector.
     *
     * @return array<string, string>
     */
    private function countryList(): array
    {
        if (function_exists('WC') && WC()->countries instanceof \WC_Countries) {
            $countries = WC()->countries->get_countries();
            if (is_array($countries)) {
                return $countries;
            }
        }

        return [];
    }
}
