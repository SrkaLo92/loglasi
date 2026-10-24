<?php

namespace App\Support\Listing;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\HtmlString;

/** Serbian text helpers for the listing and profile pages (plurals, dates, description). */
class Text
{
    public const TIMEZONE = 'Europe/Belgrade';

    private const MONTHS_GENITIVE = ['januara', 'februara', 'marta', 'aprila', 'maja', 'juna', 'jula', 'avgusta', 'septembra', 'oktobra', 'novembra', 'decembra'];

    /** Serbian plural: 1, 21 / 2–4, 22–24 / the rest. */
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

    /** "1.234" */
    public static function number(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }

    /** "1 aktivan oglas", "3 aktivna oglasa", "12 aktivnih oglasa". */
    public static function activeListings(int $n): string
    {
        return static::number($n).' '.static::plural($n, 'aktivan oglas', 'aktivna oglasa', 'aktivnih oglasa');
    }

    /** "1.234 pregleda" */
    public static function views(int $n): string
    {
        return static::number($n).' '.static::plural($n, 'pregled', 'pregleda', 'pregleda');
    }

    /** "oktobra 2026." */
    public static function monthYear(?DateTimeInterface $date): string
    {
        if ($date === null) {
            return '';
        }
        $local = CarbonImmutable::instance($date)->setTimezone(self::TIMEZONE);

        return self::MONTHS_GENITIVE[$local->month - 1].' '.$local->year.'.';
    }

    /** "Na sajtu od oktobra 2026." */
    public static function memberSince(?DateTimeInterface $date): string
    {
        $text = static::monthYear($date);

        return $text === '' ? '' : 'Na sajtu od '.$text;
    }

    /** Exact local time for the title attribute: "23.10.2026. u 14:05". */
    public static function exact(DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)->setTimezone(self::TIMEZONE)->format('j.n.Y. \u H:i');
    }

    /** "upravo", "pre 5 minuta", "pre 2 sata", "juče", "pre 3 dana", "pre 2 nedelje", "pre 3 meseca", "pre 2 godine". */
    public static function relative(DateTimeInterface $date, ?DateTimeInterface $now = null): string
    {
        $now = CarbonImmutable::instance($now ?? now())->setTimezone(self::TIMEZONE);
        $then = CarbonImmutable::instance($date)->setTimezone(self::TIMEZONE);
        $diff = $now->getTimestamp() - $then->getTimestamp();

        if ($diff < 60) {
            return 'upravo';
        }
        if ($diff < 3600) {
            $n = intdiv($diff, 60);

            return 'pre '.$n.' '.static::plural($n, 'minut', 'minuta', 'minuta');
        }

        $days = (int) $then->startOfDay()->diffInDays($now->startOfDay());

        if ($days === 0) {
            $n = intdiv($diff, 3600);

            return 'pre '.$n.' '.static::plural($n, 'sat', 'sata', 'sati');
        }
        if ($days === 1) {
            return 'juče';
        }
        if ($days < 7) {
            return 'pre '.$days.' dana';
        }
        if ($days < 30) {
            $n = intdiv($days, 7);

            return 'pre '.$n.' '.static::plural($n, 'nedelju', 'nedelje', 'nedelja');
        }
        if ($days < 365) {
            $n = max(1, intdiv($days, 30));

            return 'pre '.$n.' '.static::plural($n, 'mesec', 'meseca', 'meseci');
        }
        $n = intdiv($days, 365);

        return 'pre '.$n.' '.static::plural($n, 'godinu', 'godine', 'godina');
    }

    /** Seller card initials: first letters of the first two words ("Auto centar Drina" → "AC"). */
    public static function wordInitials(string $name): string
    {
        $words = preg_split('/\s+/u', trim(strip_tags($name)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $out .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $out !== '' ? $out : '?';
    }

    /** Profile logo initials: "Auto servis Drina d.o.o." → "AD". */
    public static function initials(string $name): string
    {
        $words = preg_split('/[\s\-–.,&]+/u', strip_tags($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_values(array_filter($words, fn ($w) => (bool) preg_match('/\p{L}/u', $w)));
        $long = array_values(array_filter($words, fn ($w) => mb_strlen($w) > 1));
        $words = $long ?: $words;
        if ($words === []) {
            return '?';
        }
        $first = mb_substr($words[0], 0, 1);
        $last = count($words) > 1 ? mb_substr((string) end($words), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    /**
     * Plain-text description as safe HTML: escaped, paragraphs on blank lines, line breaks,
     * and http(s) links with rel="nofollow ugc noopener".
     */
    public static function description(?string $text): HtmlString
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
        if ($text === '') {
            return new HtmlString('');
        }

        $paragraphs = preg_split("/\n{2,}/", $text) ?: [];
        $html = '';
        foreach ($paragraphs as $paragraph) {
            $html .= '<p>'.nl2br(static::linkify(trim($paragraph)), false).'</p>';
        }

        return new HtmlString($html);
    }

    /** Escapes text and turns http(s)://… and www.… into links. */
    public static function linkify(string $text): string
    {
        $pattern = '~\b((?:https?://|www\.)[^\s<>"\']+)~iu';
        $out = '';
        $offset = 0;

        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as [$url, $pos]) {
            // Trailing punctuation stays outside the link.
            $trimmed = rtrim($url, '.,;:!?)]}»”');
            $out .= e(substr($text, $offset, $pos - $offset));
            $href = str_starts_with(strtolower($trimmed), 'www.') ? 'https://'.$trimmed : $trimmed;
            if (filter_var($href, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $href)) {
                $out .= '<a href="'.e($href).'" rel="nofollow ugc noopener" target="_blank">'.e($trimmed).'</a>';
            } else {
                $out .= e($trimmed);
            }
            $offset = $pos + strlen($trimmed);
        }

        return $out.e(substr($text, $offset));
    }
}
