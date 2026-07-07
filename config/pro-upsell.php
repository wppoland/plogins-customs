<?php
/**
 * PRO upsell content, generated from the plogins.com registry by
 * scripts/gen-pro-upsell.mjs. The admin upsell renders this; curate the
 * feature list to fit this plugin's settings screen (do not invent features).
 *
 * @package plogins-customs-pro
 */

defined('ABSPATH') || exit;

return [
    'name'       => 'Customs Pro',
    'url'        => 'https://plogins.com/plogins-customs-pro/pricing/',
    'sellable'   => false,
    'price_from' => 0,
    'currency'   => 'EUR',
    'price_pln'  => 0,
    'lead'       => [
        'en' => 'Customs Pro is in preparation. The features below are planned after Customs FREE is approved on WordPress.org.',
        'pl' => 'Customs Pro jest w przygotowaniu. Poniższe funkcje są planowane po akceptacji Customs FREE na WordPress.org.',
    ],
    'features'   => [
        [
            'en' => ['title' => 'Live exchange rates', 'desc' => 'Automatic EUR to store-currency conversion instead of a manual rate in settings.'],
            'pl' => ['title' => 'Kursy walut na żywo', 'desc' => 'Automatyczne przeliczanie EUR na walutę sklepu zamiast ręcznego kursu w ustawieniach.'],
        ],
        [
            'en' => ['title' => 'HS and tariff codes', 'desc' => 'Product classification support and more accurate tariff-line counting in the cart.'],
            'pl' => ['title' => 'Kody HS i taryfowe', 'desc' => 'Wsparcie klasyfikacji produktów i dokładniejsze liczenie linii taryfowych w koszyku.'],
        ],
        [
            'en' => ['title' => 'Handling fees', 'desc' => 'Optional estimates for national clearance fees on top of the import duty itself.'],
            'pl' => ['title' => 'Opłaty manipulacyjne', 'desc' => 'Opcjonalne szacowanie krajowych opłat za odprawę oprócz samego cła importowego.'],
        ],
    ],
];
