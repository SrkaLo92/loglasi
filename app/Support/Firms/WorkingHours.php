<?php

namespace App\Support\Firms;

/**
 * Working hours as saved in firms.working_hours: {"1": "08–16", ..., "7": "Zatvoreno"}
 * (ISO day, Monday = 1, free text up to 40 characters), the same shape the importer writes.
 * Also reads {"mon": "..."} and [{"day", "open", "close"}]. For the API the hours are one string,
 * a line per day: "Ponedeljak: 08–16".
 */
class WorkingHours
{
    public const MAX_LENGTH = 40;

    private const PREFIXES = ['pon' => 1, 'uto' => 2, 'sre' => 3, 'čet' => 4, 'cet' => 4, 'pet' => 5, 'sub' => 6, 'ned' => 7];

    public const DAYS = [
        1 => 'Ponedeljak', 2 => 'Utorak', 3 => 'Sreda', 4 => 'Četvrtak', 5 => 'Petak', 6 => 'Subota', 7 => 'Nedelja',
    ];

    /** @return array<int, string> seven days, empty string = nothing entered */
    public static function days(mixed $saved): array
    {
        $names = ['mon' => 1, 'tue' => 2, 'wed' => 3, 'thu' => 4, 'fri' => 5, 'sat' => 6, 'sun' => 7];
        $byDay = [];

        foreach (is_array($saved) ? $saved : [] as $key => $value) {
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

    /**
     * Value to save from seven inputs; null when every day is empty.
     *
     * @param  array<int|string, mixed>  $input
     * @return array<string, string>|null
     */
    public static function fromInput(array $input): ?array
    {
        $hours = [];
        foreach (array_keys(self::DAYS) as $day) {
            $hours[(string) $day] = static::clean($input[$day] ?? $input[(string) $day] ?? '');
        }

        return implode('', $hours) === '' ? null : $hours;
    }

    /** "Ponedeljak: 08–16\nUtorak: ..." (only days with text); empty string when nothing is entered. */
    public static function toText(mixed $saved): string
    {
        $lines = [];
        foreach (static::days($saved) as $day => $text) {
            if ($text !== '') {
                $lines[] = self::DAYS[$day].': '.$text;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Parses the API string: one line per day, "Pon: 08-16", "Ponedeljak 08–16", "Pon–Pet: 8–16", "Sub - Zatvoreno".
     * Returns null for an empty string, false when a line has no known day.
     *
     * @return array<string, string>|false|null
     */
    public static function fromText(string $text): array|false|null
    {
        $input = [];
        foreach (preg_split('/\R/u', trim($text)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $day = '(pon|uto|sre|čet|cet|pet|sub|ned)[\p{L}]*\.?';
            if (! preg_match('/^'.$day.'(?:\s*[-–]\s*'.$day.')?\s*(.*)$/iu', $line, $m)) {
                return false;
            }
            $from = self::PREFIXES[mb_strtolower($m[1])];
            $to = $m[2] !== '' ? self::PREFIXES[mb_strtolower($m[2])] : $from;
            $value = trim((string) preg_replace('/^[\s:\-–—]+/u', '', $m[3]));
            if (mb_strlen($value) > self::MAX_LENGTH || $to < $from) {
                return false;
            }
            foreach (range($from, $to) as $d) {
                $input[$d] = $value;
            }
        }

        return static::fromInput($input);
    }

    private static function clean(mixed $value): string
    {
        $value = is_scalar($value) ? (string) $value : '';

        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags($value))), 0, self::MAX_LENGTH);
    }
}
