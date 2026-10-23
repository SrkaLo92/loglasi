<?php

namespace App\Support\Listing;

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

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }
}
