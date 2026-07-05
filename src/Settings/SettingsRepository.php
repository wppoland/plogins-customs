<?php

declare(strict_types=1);

namespace Customs\Settings;

defined('ABSPATH') || exit;

/**
 * Reads, normalises and persists the plugin settings stored in the
 * `customs_settings` option. Single source of truth for the settings shape, so
 * the admin UI, the calculator and the fee applicator all agree on structure
 * and types.
 *
 * EUR is the canonical unit for the per-line amount and the threshold, because
 * the EU rule is written in EUR. Conversion into the store currency happens
 * through eur_rate at calculation time.
 */
final class SettingsRepository
{
    public const OPTION = 'customs_settings';

    public const BASIS_CATEGORY = 'category';
    public const BASIS_PRODUCT  = 'product';

    public function isEnabled(): bool
    {
        return (bool) ($this->settings()['enabled'] ?? false);
    }

    /**
     * Flat duty per distinct tariff line, in EUR.
     */
    public function perLineEur(): float
    {
        return max(0.0, (float) ($this->settings()['per_line'] ?? 0.0));
    }

    /**
     * Goods-value ceiling the duty applies under, in EUR.
     */
    public function thresholdEur(): float
    {
        return max(0.0, (float) ($this->settings()['threshold'] ?? 0.0));
    }

    /**
     * Store-currency units per 1 EUR. Never returns <= 0 so it is always safe
     * to multiply by.
     */
    public function eurRate(): float
    {
        $rate = (float) ($this->settings()['eur_rate'] ?? 1.0);

        return $rate > 0 ? $rate : 1.0;
    }

    /**
     * Origin country ISO code. Falls back to the WooCommerce base country when
     * no explicit origin is configured.
     */
    public function originCountry(): string
    {
        $origin = strtoupper(trim((string) ($this->settings()['origin_country'] ?? '')));
        if ('' !== $origin) {
            return $origin;
        }

        $base = wc_get_base_location();

        return strtoupper((string) ($base['country'] ?? ''));
    }

    public function countBasis(): string
    {
        $basis = (string) ($this->settings()['count_basis'] ?? self::BASIS_CATEGORY);

        return self::BASIS_PRODUCT === $basis ? self::BASIS_PRODUCT : self::BASIS_CATEGORY;
    }

    public function label(): string
    {
        $label = trim((string) ($this->settings()['label'] ?? ''));

        return '' !== $label ? $label : __('EU import duty (estimate)', 'plogins-customs');
    }

    public function isTaxable(): bool
    {
        return (bool) ($this->settings()['taxable'] ?? false);
    }

    /**
     * Settings array merged over packaged defaults.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $stored = get_option(self::OPTION, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        /** @var array<string, mixed> $defaults */
        $defaults = require CUSTOMS_DIR . 'config/defaults.php';

        return array_merge($defaults, $stored);
    }

    /**
     * Coerce a raw submitted settings array into the canonical shape with safe
     * types. Used by the admin save path.
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    public function normalize(array $raw): array
    {
        $basis = (string) ($raw['count_basis'] ?? self::BASIS_CATEGORY);
        if (! in_array($basis, [self::BASIS_CATEGORY, self::BASIS_PRODUCT], true)) {
            $basis = self::BASIS_CATEGORY;
        }

        $origin = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($raw['origin_country'] ?? '')) ?? '');
        if (strlen($origin) !== 2) {
            $origin = '';
        }

        $rate = (float) ($raw['eur_rate'] ?? 1.0);

        return [
            'enabled'        => ! empty($raw['enabled']),
            'per_line'       => max(0.0, (float) ($raw['per_line'] ?? 0.0)),
            'threshold'      => max(0.0, (float) ($raw['threshold'] ?? 0.0)),
            'eur_rate'       => $rate > 0 ? $rate : 1.0,
            'origin_country' => $origin,
            'count_basis'    => $basis,
            'label'          => sanitize_text_field((string) ($raw['label'] ?? '')),
            'taxable'        => ! empty($raw['taxable']),
        ];
    }
}
