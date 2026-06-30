<?php

declare(strict_types=1);

namespace Plogins\Customs\Geo;

defined('ABSPATH') || exit;

/**
 * Decides whether a country belongs to the EU customs territory.
 *
 * The EU import duty applies to parcels shipped into the EU from outside it, so
 * both the destination test (must be inside) and the origin test (must be
 * outside) lean on this single membership list. Kept deliberately simple for
 * the FREE MVP: special customs territories and edge regions are not modelled
 * here, and the list can be adjusted through the customs/eu_countries filter.
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

    public function isMember(string $country): bool
    {
        $country = strtoupper(trim($country));
        if ('' === $country) {
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
