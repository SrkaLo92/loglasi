<?php

namespace App\Support\Firms;

use App\Support\Listing\WorkingHours;

/**
 * Input side of firm working hours: seven form fields or the API string ("Pon–Pet: 08–16", a line per
 * day) to the saved shape {"1": "08–16", ..., "7": "Zatvoreno"} (ISO day, Monday = 1, free text up to
 * 40 characters), the same shape the importer writes. Reading and display: App\Support\Listing\WorkingHours.
 */
class WorkingHoursInput
{
    public const MAX_LENGTH = 40;

    private const PREFIXES = ['pon' => 1, 'uto' => 2, 'sre' => 3, 'čet' => 4, 'cet' => 4, 'pet' => 5, 'sub' => 6, 'ned' => 7];

    /**
     * Value to save from seven inputs; null when every day is empty.
     *
     * @param  array<int|string, mixed>  $input
     * @return array<string, string>|null
     */
    public static function fromInput(array $input): ?array
    {
        $hours = [];
        foreach (array_keys(WorkingHours::DAYS) as $day) {
            $hours[(string) $day] = static::clean($input[$day] ?? $input[(string) $day] ?? '');
        }

        return implode('', $hours) === '' ? null : $hours;
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
