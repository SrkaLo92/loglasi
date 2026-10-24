<?php

namespace App\Support\Search;

use Carbon\CarbonInterface;

/** Relative time in Serbian: „upravo”, „pre 5 minuta”, „pre 2 sata”, „juče”, „pre 3 dana”. */
class TimeAgo
{
    public static function format(?CarbonInterface $time, ?CarbonInterface $now = null): string
    {
        if ($time === null) {
            return '';
        }

        $now ??= now();
        $diff = max(0, $now->getTimestamp() - $time->getTimestamp());

        if ($diff < 60) {
            return 'upravo';
        }
        if ($diff < 3600) {
            $n = intdiv($diff, 60);

            return 'pre '.$n.' '.self::plural($n, 'minut', 'minuta', 'minuta');
        }
        if ($diff < 86400) {
            $n = intdiv($diff, 3600);

            return 'pre '.$n.' '.self::plural($n, 'sat', 'sata', 'sati');
        }

        $tz = config('app.timezone');
        $days = (int) $time->copy()->setTimezone($tz)->startOfDay()->diffInDays($now->copy()->setTimezone($tz)->startOfDay());

        if ($days <= 1) {
            return 'juče';
        }
        if ($days < 7) {
            return 'pre '.$days.' dana';
        }
        if ($days < 30) {
            $n = intdiv($days, 7);

            return 'pre '.$n.' '.self::plural($n, 'nedelju', 'nedelje', 'nedelja');
        }
        if ($days < 365) {
            $n = max(1, intdiv($days, 30));

            return 'pre '.$n.' '.self::plural($n, 'mesec', 'meseca', 'meseci');
        }

        $n = intdiv($days, 365);

        return 'pre '.$n.' '.self::plural($n, 'godinu', 'godine', 'godina');
    }

    /** 1 oglas, 2 oglasa, 5 oglasa, 21 oglas. */
    public static function plural(int $n, string $one, string $few, string $many): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $one;
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $few;
        }

        return $many;
    }

    /** „1.250 oglasa”. */
    public static function listings(int $n): string
    {
        return number_format($n, 0, ',', '.').' '.self::plural($n, 'oglas', 'oglasa', 'oglasa');
    }
}
