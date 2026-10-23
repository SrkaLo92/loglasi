<?php

namespace App\Support\Stranice;

/**
 * Broj telefona: unos u bilo kom obliku → E.164 (+381651234567) i prikaz „+381 65 123 4567”.
 * Prepisano iz loglasi-core/inc/zajednicko/telefon.php.
 */
class Telefon
{
    private const COUNTRY = '381';

    /** E.164 ili prazan string kad broj nije ispravan. */
    public static function normalize(?string $raw): string
    {
        $raw = trim(strip_tags((string) $raw));
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
                // Strani broj: kod suseda i dijaspore vodeća nula iza pozivnog broja se ne bira.
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

        return self::validNational($national) ? '+'.self::COUNTRY.$national : '';
    }

    /** Mobilni: 6x + 6–8 cifara. Fiksni: 1x–3x, ukupno 8–9 cifara. */
    public static function validNational(string $national): bool
    {
        if (! preg_match('/^\d+$/', $national)) {
            return false;
        }
        $len = strlen($national);

        if ($national[0] === '6') {
            return $len >= 8 && $len <= 10;
        }

        return in_array($national[0], ['1', '2', '3'], true) && $len >= 8 && $len <= 9;
    }

    /** „+381 65 123 4567”; prazno kad broj nije ispravan. */
    public static function display(?string $phone): string
    {
        $e164 = self::normalize($phone);
        if ($e164 === '') {
            return '';
        }

        $digits = substr($e164, 1);
        if (! str_starts_with($digits, self::COUNTRY)) {
            return $e164;
        }

        $national = substr($digits, strlen(self::COUNTRY));
        // Trocifreni područni kodovi (0230, 0280, 0290); ostali su dvocifreni.
        $areaLen = preg_match('/^(23|28|29)/', $national) ? 3 : 2;
        $area = substr($national, 0, $areaLen);
        $number = substr($national, $areaLen);

        $len = strlen($number);
        $group = match (true) {
            $len <= 4 => $number,
            $len === 8 => substr($number, 0, 4).' '.substr($number, 4),
            default => substr($number, 0, 3).' '.substr($number, 3),
        };

        return '+'.self::COUNTRY.' '.$area.' '.$group;
    }
}
