<?php

namespace App\Services\Payments;

use App\Support\Settings;

/** Which fiscal driver is on: admin setting fiscal.driver, else config('fiscal.driver'); "manual" or "vpfr". */
final class FiscalDriver
{
    public const MANUAL = 'manual';

    public const VPFR = 'vpfr';

    public const OPTIONS = [
        self::MANUAL => 'Ručno (račun se izdaje u aplikaciji kase, broj se upisuje ovde)',
        self::VPFR => 'Automatski preko V-PFR-a',
    ];

    public static function current(): string
    {
        $driver = (string) (Settings::get('fiscal.driver') ?: config('fiscal.driver', self::MANUAL));

        return array_key_exists($driver, self::OPTIONS) ? $driver : self::MANUAL;
    }

    public static function automatic(): bool
    {
        return self::current() === self::VPFR;
    }
}
