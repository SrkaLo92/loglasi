<?php

namespace App\Support;

/**
 * Phone number: stored and used in E.164 form (+381655415128).
 * Display for people goes through display(), links through links().
 * Ported from loglasi-core (inc/zajednicko/telefon.php), with the same behaviour.
 */
class Phone
{
    /** Country calling code without the + sign. */
    public const COUNTRY = '381';

    /**
     * Brings an entered number to E.164 (+381…).
     *
     * Examples that give +381655415128: 0655415128, 065/541-5128, +381 65 541 5128,
     * 381655415128, 00381655415128, +3810655415128 (leading zero after the country code).
     * Landline: 015 123456 → +38115123456. Foreign numbers with + or 00 stay in E.164 form.
     *
     * @return string E.164 number, or '' if the number is not valid.
     */
    public static function normalize(?string $raw): string
    {
        $raw = trim(self::stripTags((string) $raw));
        if ($raw === '') {
            return '';
        }

        // A "tel:" prefix and an extension (ext.) are dropped.
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
                // the leading zero after the country code is not dialled, so it is removed.
                $digits = (string) preg_replace('/^(38[2-9]|4[139])0+/', '$1', $digits);
                $len = strlen($digits);

                return ($len >= 8 && $len <= 15 && $digits[0] !== '0') ? '+'.$digits : '';
            }
            $national = substr($digits, strlen($cc));
        } elseif (str_starts_with($digits, $cc) && strlen($digits) >= 11) {
            // 381655415128 without the + sign.
            $national = substr($digits, strlen($cc));
        } else {
            $national = $digits;
        }

        // Leading zero after the country code, or the national form 06x / 0xx.
        $national = ltrim($national, '0');

        if (! self::isValidNational($national)) {
            return '';
        }

        return '+'.$cc.$national;
    }

    /** Whether the number is valid (Serbian, or foreign in international form). */
    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== '';
    }

    /** Whether the number is Serbian (+381). */
    public static function isSerbian(?string $raw): bool
    {
        return str_starts_with(self::normalize($raw), '+'.self::COUNTRY);
    }

    /** Whether the number is a Serbian mobile (06x), for the WhatsApp and Viber buttons. */
    public static function isMobile(?string $raw): bool
    {
        return str_starts_with(self::normalize($raw), '+'.self::COUNTRY.'6');
    }

    /**
     * Checks the national part of a Serbian number (without 0 and without +381).
     * Mobile: 6x + 6–8 digits. Landline: 1x–3x, 8–9 digits in total.
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
     * Splits the number into the country code, network/area code and the rest.
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
        // Three-digit area codes (0230, 0280, 0290); the others have two digits.
        $areaLen = preg_match('/^(23|28|29)/', $national) ? 3 : 2;

        return [
            'cc' => $cc,
            'area' => substr($national, 0, $areaLen),
            'number' => substr($national, $areaLen),
        ];
    }

    /** Grouping of the subscriber part: 5415128 → "541 5128", 123456 → "123 456". */
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
     * Number for display: "+381 65 541 5128", or the national form "065 541 5128".
     *
     * @return string '' if the number is not valid.
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

    /** National form for display: "065 541 5128". */
    public static function national(?string $phone): string
    {
        return self::display($phone, true);
    }

    /** Partly hidden number before the click: "+381 65 541 ••••" or "065 541 ••••". */
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
     * Links for calls and messages.
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

    /** Same as wp_strip_all_tags(): removes script/style with their content, then the other tags. */
    private static function stripTags(string $value): string
    {
        $value = (string) preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $value);

        return strip_tags($value);
    }
}
