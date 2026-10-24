<?php

namespace Database\Seeders;

use App\Support\Settings;
use Illuminate\Database\Seeder;

/** Payee of the bank transfers (Alphasoft Dev), set only where the admin has not entered anything. */
class PayeeSeeder extends Seeder
{
    public const VALUES = [
        'payment_recipient' => "SRĐAN MARINKOVIĆ PR ALPHASOFT DEV\nMarka Kulića 57G, Klupci\n15300 Loznica",
        'payment_account' => '265-1100310109962-60',
        'payment_pib' => '115879910',
        'payment_mb' => '68713919',
    ];

    public function run(): void
    {
        foreach (self::VALUES as $key => $value) {
            if (trim((string) Settings::get($key, '')) === '') {
                Settings::set($key, $value);
            }
        }
    }
}
