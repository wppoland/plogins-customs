<?php
/**
 * Ceuta and Melilla are Spain to WooCommerce (country ES) but outside the
 * customs territory of the Union (Regulation (EU) No 952/2013, Article 4(1)).
 * Membership is decided by the Spanish postcode: 51 Ceuta, 52 Melilla.
 *
 * Run: php tests/ceuta-melilla-check.php
 *
 * @package plogins-customs
 */

define('ABSPATH', __DIR__);

function apply_filters(string $hook, $value, ...$args)
{
    return $value;
}

require __DIR__ . '/../src/Geo/EuMembership.php';

$eu   = new Customs\Geo\EuMembership();
$fail = 0;

$cases = [
    ['ES', '',      true,  'Spain without a postcode is inside'],
    ['ES', '28001', true,  'Madrid is inside'],
    ['ES', '35001', true,  'Las Palmas (Canary Islands) is inside the customs territory'],
    ['ES', '51001', false, 'Ceuta is outside'],
    ['ES', ' 52006 ', false, 'Melilla is outside, whitespace trimmed'],
    ['ES', '5100',  true,  'a four-digit 51 prefix is no Ceuta postcode'],
    ['DE', '51063', true,  'a German 51xxx postcode (Cologne) is still inside'],
    ['GB', '51001', false, 'the UK is outside regardless of postcode'],
];

foreach ($cases as [$country, $postcode, $want, $label]) {
    $got = $eu->isMember($country, $postcode);
    if ($got !== $want) {
        fwrite(STDERR, sprintf("FAIL %s: got %s\n", $label, var_export($got, true)));
        $fail = 1;
        continue;
    }
    printf("ok   %s\n", $label);
}

if ($fail) {
    exit(1);
}

echo "all Ceuta and Melilla membership checks passed\n";
