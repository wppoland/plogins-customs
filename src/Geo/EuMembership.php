<?php

declare(strict_types=1);

namespace Customs\Geo;

defined('ABSPATH') || exit;

/**
 * Decides whether a country belongs to the EU customs territory.
 *
 * The EU import duty applies to parcels shipped into the EU from outside it, so
 * both the destination test (must be inside) and the origin test (must be
 * outside) lean on this single membership list. Ceuta and Melilla are the one
 * edge modelled: WooCommerce files them under ES, but the customs territory of
 * the Union covers Spain "except Ceuta and Melilla" (Regulation (EU) No
 * 952/2013, Article 4(1)), so the Spanish provinces Ceuta and Melilla, or a
 * Spanish postcode starting 51 (Ceuta) or 52 (Melilla), are outside. Other edges can be adjusted through the
 * customs/eu_countries filter.
 */
final class EuMembership
{
    /**
     * EU-27 member states, ISO-3166 alpha-2 codes.
     *
     * @var array<int, string>
     */
    private const COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    /**
     * @param string $country  ISO alpha-2 code.
     * @param string $postcode Optional postcode, used to tell Ceuta and Melilla
     *                         apart from the rest of Spain.
     * @param string $state    Optional WooCommerce state code: ES:CE is Ceuta,
     *                         ES:ML is Melilla.
     */
    public function isMember(string $country, string $postcode = '', string $state = ''): bool
    {
        $country = strtoupper(trim($country));
        if ('' === $country) {
            return false;
        }

        if ('ES' === $country && (
            in_array(strtoupper(trim($state)), ['CE', 'ML'], true)
            || 1 === preg_match('/^5[12]\d{3}$/', trim($postcode))
        )) {
            return false;
        }

        return in_array($country, $this->countries(), true);
    }

    /**
     * The active EU country list. Filterable so a store can correct edge cases
     * (for instance a special customs territory) without editing the plugin.
     *
     * @return array<int, string>
     */
    public function countries(): array
    {
        /**
         * Filters the list of ISO alpha-2 codes treated as inside the EU.
         *
         * @param array<int, string> $countries EU-27 member states.
         */
        $countries = apply_filters('customs/eu_countries', self::COUNTRIES);

        return array_values(array_unique(array_map(
            static fn ($code): string => strtoupper(trim((string) $code)),
            is_array($countries) ? $countries : self::COUNTRIES
        )));
    }
}
