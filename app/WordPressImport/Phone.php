<?php

namespace App\WordPressImport;

use App\Support\Phone as SitePhone;

/**
 * Phone number in E.164 (+381...), the same rule as loglasi_phone_normalize()
 * and loglasi_oglas_join_phone() from loglasi-core.
 */
class Phone
{
    /** Joins the number and a separately stored country code ("+381" + "0655415128"). */
    public static function join(string $raw, string $country = ''): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        // Listivo stores a flag as an HTML entity before the code ("&#x1F1F7&#x1F1F8 +381"): only +NNN is taken.
        $country = preg_match('/\+\s*(\d{1,4})/', $country, $m) ? $m[1] : (string) preg_replace('/\D+/', '', $country);
        if ($country !== ''
            && ! str_starts_with($raw, '+')
            && ! str_starts_with($raw, '00')
            && ! str_starts_with((string) preg_replace('/\D+/', '', $raw), $country)) {
            $raw = '+'.$country.$raw;
        }

        return static::normalize($raw);
    }

    /** E.164, or '' when the number is not valid (the site's own rule). */
    public static function normalize(string $raw): string
    {
        return SitePhone::normalize($raw);
    }
}
