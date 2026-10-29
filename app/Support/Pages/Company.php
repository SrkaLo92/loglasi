<?php

namespace App\Support\Pages;

use App\Support\Phone;
use App\Support\Settings;

/**
 * The business that runs the site (impresum, Zakon o elektronskoj trgovini čl. 6): admin settings
 * company_* with the registered sole trader as default. Shown in the footer, on the terms and privacy
 * pages, in SellerIdentity and in the Organization JSON-LD.
 */
class Company
{
    public const DEFAULTS = [
        'company_name' => 'SRĐAN MARINKOVIĆ PR VEB PORTALI ALPHASOFT DEV KLUPCI',
        'company_address' => 'Marka Kulića 57G, Klupci, 15300 Loznica',
        'company_mb' => '68713919',
        'company_pib' => '115879910',
        'company_register' => 'Registrovan u Agenciji za privredne registre (APR)',
    ];

    public static function name(): string
    {
        return self::get('company_name');
    }

    public static function address(): string
    {
        return self::get('company_address');
    }

    public static function mb(): string
    {
        return self::get('company_mb');
    }

    public static function pib(): string
    {
        return self::get('company_pib');
    }

    public static function register(): string
    {
        return self::get('company_register');
    }

    public static function email(): string
    {
        return trim((string) Settings::get('contact_email'));
    }

    public static function phone(): string
    {
        $phone = trim((string) Settings::get('contact_phone'));

        return $phone !== '' ? Phone::display($phone) : '';
    }

    /** "Sajtom upravlja NAME, ADDRESS. MB …, PIB …. Registrovan …" without the contact. */
    public static function line(): string
    {
        $parts = array_filter([
            self::name() !== '' ? 'Sajtom upravlja '.self::name().(self::address() !== '' ? ', '.self::address() : '').'.' : '',
            self::mb() !== '' ? 'MB '.self::mb().(self::pib() !== '' ? ', PIB '.self::pib() : '').'.' : (self::pib() !== '' ? 'PIB '.self::pib().'.' : ''),
            self::register() !== '' ? rtrim(self::register(), '.').'.' : '',
        ]);

        return implode(' ', $parts);
    }

    private static function get(string $key): string
    {
        return trim((string) Settings::get($key, self::DEFAULTS[$key]));
    }
}
