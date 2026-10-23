<?php

namespace App\Support;

/**
 * Broj telefona: čuva se i koristi u E.164 obliku (+381655415128).
 * Prikaz za ljude ide kroz display(), linkovi kroz links().
 * Prenet iz loglasi-core (inc/zajednicko/telefon.php), ponašanje je isto.
 */
class Phone
{
    /** Pozivni broj države bez znaka +. */
    public const COUNTRY = '381';

    /**
     * Svodi uneti broj na E.164 (+381…).
     *
     * Primeri koji daju +381655415128: 0655415128, 065/541-5128, +381 65 541 5128,
     * 381655415128, 00381655415128, +3810655415128 (vodeća nula iza pozivnog broja).
     * Fiksni: 015 123456 → +38115123456. Strani brojevi sa + ili 00 ostaju u E.164 obliku.
     *
     * @return string E.164 broj ili '' ako broj nije ispravan.
     */
    public static function normalize(?string $raw): string
    {
        $raw = trim(self::stripTags((string) $raw));
        if ($raw === '') {
            return '';
        }

        // Prefiks "tel:" i lokal (ext.) se odbacuju.
        $raw = (string) preg_replace('/^(tel:|callto:)/i', '', $raw);
        $raw = (string) preg_replace('/\s*(ext\.?|lok\.?|x)\s*\d+$/i', '', $raw);

        $hasPlus = str_starts_with(ltrim($raw), '+');
        $digits = (string) preg_replace('/\D+/', '', $raw);

        if ($digits === '' || strlen($digits) > 16) {
            return '';
        }

        $cc = self::COUNTRY;

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $hasPlus = true;
        }

        if ($hasPlus) {
            if (! str_starts_with($digits, $cc)) {
                // Strani broj. Kod suseda i dijaspore (BiH, CG, HR, SLO, MK, AT, DE, CH)
                // vodeća nula iza pozivnog broja se ne bira, pa se skida.
                $digits = (string) preg_replace('/^(38[2-9]|4[139])0+/', '$1', $digits);
                $len = strlen($digits);

                return ($len >= 8 && $len <= 15 && $digits[0] !== '0') ? '+'.$digits : '';
            }
            $national = substr($digits, strlen($cc));
        } elseif (str_starts_with($digits, $cc) && strlen($digits) >= 11) {
            // 381655415128 bez znaka +.
            $national = substr($digits, strlen($cc));
        } else {
            $national = $digits;
        }

        // Vodeća nula iza pozivnog broja ili domaći oblik 06x / 0xx.
        $national = ltrim($national, '0');

        if (! self::isValidNational($national)) {
            return '';
        }

        return '+'.$cc.$national;
    }

    /** Da li je broj ispravan (srpski ili strani u međunarodnom obliku). */
    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== '';
    }

    /** Da li je broj srpski (+381). */
    public static function isSerbian(?string $raw): bool
    {
        return str_starts_with(self::normalize($raw), '+'.self::COUNTRY);
    }

    /** Da li je broj srpski mobilni (06x), za WhatsApp i Viber dugmad. */
    public static function isMobile(?string $raw): bool
    {
        return str_starts_with(self::normalize($raw), '+'.self::COUNTRY.'6');
    }

    /**
     * Provera nacionalnog dela srpskog broja (bez 0 i bez +381).
     * Mobilni: 6x + 6–8 cifara. Fiksni: 1x–3x, ukupno 8–9 cifara.
     */
    public static function isValidNational(string $national): bool
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

    /**
     * Deli broj na pozivni broj, mrežni/područni kod i ostatak.
     *
     * @return array{cc:string, area:string, number:string}|null
     */
    public static function parts(?string $phone): ?array
    {
        $e164 = self::normalize($phone);
        if ($e164 === '') {
            return null;
        }
        $digits = substr($e164, 1);
        $cc = self::COUNTRY;

        if (! str_starts_with($digits, $cc)) {
            return ['cc' => '', 'area' => '', 'number' => $digits];
        }

        $national = substr($digits, strlen($cc));
        // Trocifreni područni kodovi (0230, 0280, 0290); ostali su dvocifreni.
        $areaLen = preg_match('/^(23|28|29)/', $national) ? 3 : 2;

        return [
            'cc' => $cc,
            'area' => substr($national, 0, $areaLen),
            'number' => substr($national, $areaLen),
        ];
    }

    /** Grupisanje pretplatničkog dela: 5415128 → "541 5128", 123456 → "123 456". */
    public static function group(string $number): string
    {
        $len = strlen($number);
        if ($len <= 4) {
            return $number;
        }
        if ($len === 8) {
            return substr($number, 0, 4).' '.substr($number, 4);
        }

        return substr($number, 0, 3).' '.substr($number, 3);
    }

    /**
     * Broj za prikaz: "+381 65 541 5128", ili domaći oblik "065 541 5128".
     *
     * @return string '' ako broj nije ispravan.
     */
    public static function display(?string $phone, bool $national = false): string
    {
        $p = self::parts($phone);
        if ($p === null) {
            return '';
        }
        if ($p['cc'] === '') {
            return '+'.$p['number'];
        }
        $rest = self::group($p['number']);

        return $national
            ? '0'.$p['area'].' '.$rest
            : '+'.$p['cc'].' '.$p['area'].' '.$rest;
    }

    /** Domaći oblik za prikaz: "065 541 5128". */
    public static function national(?string $phone): string
    {
        return self::display($phone, true);
    }

    /** Delimično skriven broj pre klika: "+381 65 541 ••••" ili "065 541 ••••". */
    public static function mask(?string $phone, int $hide = 4, bool $national = false): string
    {
        $display = self::display($phone, $national);
        if ($display === '') {
            return '';
        }
        $chars = preg_split('//u', $display, -1, PREG_SPLIT_NO_EMPTY);
        $hidden = 0;
        for ($i = count($chars) - 1; $i >= 0 && $hidden < $hide; $i--) {
            if (ctype_digit($chars[$i])) {
                $chars[$i] = '•';
                $hidden++;
            }
        }

        return implode('', $chars);
    }

    /**
     * Linkovi za poziv i poruke.
     *
     * @return array{e164:string, tel:string, whatsapp:string, viber:string, sms:string}|array{}
     */
    public static function links(?string $phone): array
    {
        $e164 = self::normalize($phone);
        if ($e164 === '') {
            return [];
        }
        $digits = substr($e164, 1);

        return [
            'e164' => $e164,
            'tel' => 'tel:'.$e164,
            'whatsapp' => 'https://wa.me/'.$digits,
            'viber' => 'viber://chat?number=%2B'.$digits,
            'sms' => 'sms:'.$e164,
        ];
    }

    /** Isto kao wp_strip_all_tags(): izbacuje script/style sa sadržajem, pa ostale oznake. */
    private static function stripTags(string $value): string
    {
        $value = (string) preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $value);

        return strip_tags($value);
    }
}
