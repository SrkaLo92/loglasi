<?php

namespace App\Support\Listing;

use Carbon\CarbonImmutable;

/**
 * Firm working hours: {"1": "08-16", ..., "7": ""} (ISO day, Monday = 1), parsed into ranges
 * and an "open now" status. The same algorithm runs in public/js/listing-profile.js,
 * so the status stays correct on cached pages.
 */
class WorkingHours
{
    public const DAYS = [
        1 => ['Ponedeljak', 'Pon'],
        2 => ['Utorak', 'Uto'],
        3 => ['Sreda', 'Sre'],
        4 => ['Četvrtak', 'Čet'],
        5 => ['Petak', 'Pet'],
        6 => ['Subota', 'Sub'],
        7 => ['Nedelja', 'Ned'],
    ];

    /** Accusative day names ("otvara se u sredu"). */
    public const DAYS_ACCUSATIVE = [
        1 => 'ponedeljak', 2 => 'utorak', 3 => 'sredu', 4 => 'četvrtak', 5 => 'petak', 6 => 'subotu', 7 => 'nedelju',
    ];

    /**
     * Seven days of text; accepts {"1": ...}, {"mon": ...} or [{"day", "open", "close"}].
     *
     * @return array<int, string>
     */
    public static function days(mixed $saved): array
    {
        $saved = is_array($saved) ? $saved : [];
        $names = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];
        $byDay = [];

        foreach ($saved as $key => $value) {
            if (is_array($value) && isset($value['day'])) {
                $day = $names[strtolower(substr((string) $value['day'], 0, 3))] ?? (int) $value['day'];
                $text = trim(($value['open'] ?? '').'–'.($value['close'] ?? ''), '–');
                $byDay[$day] = isset($byDay[$day]) ? $byDay[$day].', '.$text : $text;

                continue;
            }
            $day = is_numeric($key) ? (int) $key : ($names[strtolower(substr((string) $key, 0, 3))] ?? 0);
            if ($day >= 1 && $day <= 7 && is_scalar($value)) {
                $byDay[$day] = trim((string) $value);
            }
        }

        $hours = [];
        foreach (array_keys(self::DAYS) as $day) {
            $hours[$day] = $byDay[$day] ?? '';
        }

        return $hours;
    }

    public static function hasAny(array $hours): bool
    {
        return implode('', $hours) !== '';
    }

    /**
     * Ranges in minutes from midnight: "08–16", "8:00-12:00, 13-17", "8.30–16".
     *
     * @return list<array{0: int, 1: int}>
     */
    public static function parseRanges(string $text): array
    {
        $ranges = [];
        if (! preg_match_all('/(\d{1,2})(?:[:.h](\d{2}))?\s*[-–—]\s*(\d{1,2})(?:[:.h](\d{2}))?/u', $text, $m, PREG_SET_ORDER)) {
            return $ranges;
        }
        foreach ($m as $match) {
            $from = (int) $match[1] * 60 + (int) ($match[2] ?? 0);
            $to = (int) $match[3] * 60 + (int) ($match[4] ?? 0);
            if ($from >= 0 && $to <= 1440 && $to > $from) {
                $ranges[] = [$from, $to];
            }
        }

        return $ranges;
    }

    /**
     * Per day: list of ranges, [] = closed, null = unknown.
     *
     * @param  array<int, string>  $hours
     * @return array<int, list<array{0: int, 1: int}>|null>
     */
    public static function schedule(array $hours): array
    {
        $schedule = [];
        foreach ($hours as $day => $text) {
            $ranges = $text === '' ? [] : static::parseRanges($text);
            if ($ranges !== []) {
                $schedule[$day] = $ranges;
            } elseif ($text !== '' && preg_match('/zatvoren|ne radi|neradn|^\s*-+\s*$/iu', $text)) {
                $schedule[$day] = [];
            } else {
                $schedule[$day] = null;
            }
        }

        // With at least one known day, empty days count as closed.
        if (array_filter($schedule, fn ($d) => $d !== null) !== []) {
            foreach ($hours as $day => $text) {
                if ($text === '') {
                    $schedule[$day] = [];
                }
            }
        }

        return $schedule;
    }

    /**
     * "Otvoreno do 17:00" / "Zatvoreno, otvara se u 08:00" / "… sutra u 08:00" / "… u ponedeljak u 08:00".
     *
     * @return array{open: bool, text: string}|null null when the text does not allow a conclusion.
     */
    public static function status(array $schedule, ?CarbonImmutable $now = null): ?array
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone(Text::TIMEZONE);
        $day = (int) $now->format('N');
        $min = (int) $now->format('G') * 60 + (int) $now->format('i');
        $today = $schedule[$day] ?? null;

        if ($today === null) {
            return null;
        }
        foreach ($today as [$from, $to]) {
            if ($min >= $from && $min < $to) {
                return ['open' => true, 'text' => 'Otvoreno do '.static::hhmm($to)];
            }
        }
        foreach ($today as [$from]) {
            if ($from > $min) {
                return ['open' => false, 'text' => 'Zatvoreno, otvara se u '.static::hhmm($from)];
            }
        }
        for ($i = 1; $i <= 7; $i++) {
            $next = (($day - 1 + $i) % 7) + 1;
            $ranges = $schedule[$next] ?? null;
            if ($ranges === null) {
                return null;
            }
            if ($ranges !== []) {
                $time = static::hhmm($ranges[0][0]);

                return ['open' => false, 'text' => $i === 1
                    ? 'Zatvoreno, otvara se sutra u '.$time
                    : 'Zatvoreno, otvara se u '.self::DAYS_ACCUSATIVE[$next].' u '.$time];
            }
        }

        return ['open' => false, 'text' => 'Zatvoreno'];
    }

    public static function hhmm(int $minutes): string
    {
        return $minutes === 1440 ? '24:00' : sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /** One line per filled day, for the API: "Ponedeljak: 08–16\nUtorak: ...". */
    public static function text(array $hours): string
    {
        $schedule = static::schedule($hours);
        $lines = [];
        foreach (self::DAYS as $day => [$name]) {
            if ($hours[$day] !== '') {
                $lines[] = $name.': '.$hours[$day];
            } elseif (($schedule[$day] ?? null) === [] && static::hasAny($hours)) {
                $lines[] = $name.': zatvoreno';
            }
        }

        return implode("\n", $lines);
    }
}
