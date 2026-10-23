<?php

namespace App\Support\Payments;

/** Serbian tax ID (PIB, 9 digits, ISO 7064 MOD 11,10) and registration number (MB, 8 digits). */
final class TaxId
{
    public static function isValidPib(?string $pib): bool
    {
        $pib = (string) $pib;
        if (! preg_match('/^\d{9}$/', $pib)) {
            return false;
        }

        $a = 10;
        for ($i = 0; $i < 8; $i++) {
            $a = ($a + (int) $pib[$i]) % 10;
            $a = (($a === 0 ? 10 : $a) * 2) % 11;
        }

        return (11 - $a) % 10 === (int) $pib[8];
    }

    /** Format only: entrepreneurs' numbers do not follow the legal-entity control digit. */
    public static function isValidMb(?string $mb): bool
    {
        return (bool) preg_match('/^\d{8}$/', (string) $mb);
    }
}
