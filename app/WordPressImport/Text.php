<?php

namespace App\WordPressImport;

/** Text, amounts and serialized values from the WordPress database. */
class Text
{
    /** Title without HTML and entities. */
    public static function title(string $html): string
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Plain text with new lines (like Loglasi_API_Formatter::plain_text). */
    public static function plain(string $html): string
    {
        $text = (string) preg_replace('~\[/?[a-z][a-z0-9_-]*[^\]]*\]~i', '', $html);
        $text = (string) preg_replace('~<br\s*/?>~i', "\n", $text);
        $text = (string) preg_replace('~</(p|div|li|h[1-6])>~i', "\n\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace("~[ \t]+\n~", "\n", $text);
        $text = (string) preg_replace("~\n{3,}~", "\n\n", $text);

        return trim($text);
    }

    /**
     * Amount from a meta value: "8900", "8.900", "8 900", "12500,50". Null for empty or 0
     * (the same as Loglasi_Cena::parse_amount).
     */
    public static function amount(mixed $raw): ?float
    {
        $raw = static::unserialize($raw);
        if (is_array($raw)) {
            $raw = $raw['value'] ?? reset($raw);
        }
        if (! is_scalar($raw)) {
            return null;
        }

        $raw = (string) preg_replace('/[\s\x{00A0}]+/u', '', (string) $raw);
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $raw)) {
            $raw = str_replace('.', '', $raw);
        }
        $raw = str_replace(',', '.', $raw);
        if (! is_numeric($raw)) {
            return null;
        }

        $amount = round((float) $raw, 2);

        return $amount > 0 ? $amount : null;
    }

    /** Number for a "number" field (0 allowed). */
    public static function number(mixed $raw): ?float
    {
        if (! is_scalar($raw)) {
            return null;
        }
        $raw = (string) preg_replace('/[\s\x{00A0}]+/u', '', (string) $raw);
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $raw)) {
            $raw = str_replace('.', '', $raw);
        }
        $raw = str_replace(',', '.', $raw);

        return is_numeric($raw) ? round((float) $raw, 2) : null;
    }

    /** Like maybe_unserialize(), without creating objects. */
    public static function unserialize(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $trimmed = trim($value);
        if ($trimmed === 'b:0;') {
            return false;
        }
        if (! preg_match('/^(a|O|s|i|d|b):/', $trimmed)) {
            return $value;
        }
        $result = @unserialize($trimmed, ['allowed_classes' => false]);

        return $result === false ? $value : $result;
    }

    /**
     * List of IDs: an array, a serialized array, JSON or "1,2,3".
     *
     * @return int[]
     */
    public static function idList(mixed $raw): array
    {
        $raw = static::unserialize($raw);
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : explode(',', $raw);
        }

        $ids = [];
        foreach ((array) $raw as $item) {
            if (is_array($item)) {
                $item = $item['id'] ?? ($item['ID'] ?? 0);
            }
            if (is_numeric($item) && (int) $item > 0) {
                $ids[] = (int) $item;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Flat list of values from a meta field (array, serialized, several lines).
     *
     * @param  array<int, mixed>  $values
     * @return string[]
     */
    public static function flatten(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            $value = static::unserialize($value);
            if (is_array($value)) {
                $out = array_merge($out, static::flatten(array_values($value)));
            } elseif (is_scalar($value) && trim((string) $value) !== '') {
                $out[] = trim((string) $value);
            }
        }

        return $out;
    }
}
