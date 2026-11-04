<?php

namespace App\Support\Listing;

use Illuminate\Validation\Rule;

/** Report reasons from the API contract (POST /reports), with labels for the site form. */
class ReportReasons
{
    public const ALL = [
        'prevara' => 'Prevara ili sumnjiv oglas',
        'pogresna_kategorija' => 'Pogrešna kategorija',
        'duplikat' => 'Isti oglas je objavljen više puta',
        'neprikladno' => 'Neprikladan sadržaj',
        'prodato' => 'Već je prodato',
        'drugo' => 'Drugo',
    ];

    /** A report says what is wrong, in the reporter's words. */
    public const DETAILS_MIN = 10;

    /** Rules of a report: a reason and a description ($details is the field name: details or note). */
    public static function rules(string $details = 'details'): array
    {
        return [
            'reason' => ['required', Rule::in(self::keys())],
            $details => ['required', 'string', 'min:'.self::DETAILS_MIN, 'max:1000'],
        ];
    }

    public static function messages(string $details = 'details'): array
    {
        return [
            'reason.required' => 'Izaberite razlog prijave.',
            'reason.in' => 'Izaberite razlog prijave.',
            $details.'.required' => 'Napišite šta nije u redu sa oglasom, da bismo mogli da proverimo.',
            $details.'.min' => 'Opišite problem sa bar '.self::DETAILS_MIN.' znakova.',
            $details.'.string' => 'Opis mora biti tekst.',
            $details.'.max' => 'Opis može imati najviše 1.000 znakova.',
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }
}
