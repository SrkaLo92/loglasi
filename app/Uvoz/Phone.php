<?php

namespace App\Uvoz;

/**
 * Broj telefona u E.164 (+381...), isto pravilo kao loglasi_phone_normalize()
 * i loglasi_oglas_join_phone() iz loglasi-core.
 */
class Phone
{
    private const COUNTRY = '381';

    /** Spaja broj i odvojeno sačuvan pozivni broj („+381” + „0655415128”). */
    public static function join(string $raw, string $country = ''): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Listivo čuva zastavu kao HTML entitet ispred koda ("&#x1F1F7&#x1F1F8 +381"): uzima se samo +NNN.
        $country = preg_match('/\+\s*(\d{1,4})/', $country, $m) ? $m[1] : (string) preg_replace('/\D+/', '', $country);
        if ($country !== ''
            && ! str_starts_with($raw, '+')
            && ! str_starts_with($raw, '00')
            && ! str_starts_with((string) preg_replace('/\D+/', '', $raw), $country)) {
            $raw = '+'.$country.$raw;
        }

        return static::normalize($raw);
    }

    /** E.164 ili '' kad broj nije ispravan. */
    public static function normalize(string $raw): string
    {
        $raw = trim(strip_tags($raw));
        if ($raw === '') {
            return '';
        }

        $raw = (string) preg_replace('/^(tel:|callto:)/i', '', $raw);
        $raw = (string) preg_replace('/\s*(ext\.?|lok\.?|x)\s*\d+$/i', '', $raw);

        $hasPlus = str_starts_with(ltrim($raw), '+');
        $digits = (string) preg_replace('/\D+/', '', $raw);

        if ($digits === '' || strlen($digits) > 16) {
            return '';
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $hasPlus = true;
        }

        if ($hasPlus) {
            if (! str_starts_with($digits, self::COUNTRY)) {
                // Strani broj; kod suseda i dijaspore vodeća nula iza pozivnog broja se skida.
                $digits = (string) preg_replace('/^(38[2-9]|4[139])0+/', '$1', $digits);
                $len = strlen($digits);

                return ($len >= 8 && $len <= 15 && $digits[0] !== '0') ? '+'.$digits : '';
            }
            $national = substr($digits, strlen(self::COUNTRY));
        } elseif (str_starts_with($digits, self::COUNTRY) && strlen($digits) >= 11) {
            $national = substr($digits, strlen(self::COUNTRY));
        } else {
            $national = $digits;
        }

        $national = ltrim($national, '0');

        return static::validNational($national) ? '+'.self::COUNTRY.$national : '';
    }

    /** Mobilni: 6x + 6–8 cifara. Fiksni: 1x–3x, ukupno 8–9 cifara. */
    private static function validNational(string $national): bool
    {
        if (! preg_match('/^\d+$/', $national)) {
            return false;
        }
        $len = strlen($national);

        if ($national[0] === '6') {
            return $len >= 8 && $len <= 10;
        }
        if (in_array($national[0], ['1', '2', '3'], true)) {
            return $len >= 8 && $len <= 9;
        }

        return false;
    }
}
