<?php

namespace App\Support\Search;

/**
 * Search text without diacritics: "Ćevapi u Lešnici" -> "cevapi u lesnici", "Đak" -> "djak".
 * The same normalization is used for the stored column and for the query, so
 * "cevapi" finds "ćevapi" and "ćevapi" finds "cevapi".
 */
class SearchText
{
    private const MAP = [
        'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'dj',
        'Č' => 'c', 'Ć' => 'c', 'Š' => 's', 'Ž' => 'z', 'Đ' => 'dj',
        'dž' => 'dz', 'Dž' => 'dz', 'DŽ' => 'dz',
    ];

    /** Max length of the stored column (TEXT, multi-byte safe). */
    private const MAX = 20000;

    public static function normalize(?string $text): string
    {
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strtr($text, self::MAP);
        $text = mb_strtolower($text, 'UTF-8');

        // Other Latin letters with accents (é, ü...) lose them too.
        if (class_exists(\Normalizer::class)) {
            $text = (string) \Normalizer::normalize($text, \Normalizer::FORM_D);
            $text = (string) preg_replace('/\p{Mn}+/u', '', $text);
        }

        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return mb_substr(trim($text), 0, self::MAX);
    }

    /** Fulltext indexes words of 3+ characters only (innodb_ft_min_token_size). */
    public const MIN_TOKEN = 3;

    /**
     * Shorter words and InnoDB stopwords are stored once more with this suffix
     * ("a4" -> "a4qqq"), so fulltext finds them too: "audi a4", "tv", "for".
     */
    public const SHORT_SUFFIX = 'qqq';

    /** InnoDB default stopwords of 3+ letters (they are never indexed as they are). */
    public const STOPWORDS = [
        'about', 'are', 'com', 'for', 'from', 'how', 'that', 'the', 'this', 'was', 'what',
        'when', 'where', 'who', 'will', 'with', 'und', 'www',
    ];

    /** Value for listing_search.search_text: normalized text plus padded short words. */
    public static function forListing(?string $title, ?string $description): string
    {
        $text = self::normalize(trim($title.' '.$description));
        $short = [];
        foreach (array_unique(explode(' ', $text)) as $word) {
            if ($word !== '' && self::isShort($word)) {
                $short[] = $word.self::SHORT_SUFFIX;
            }
        }

        return trim($text.' '.implode(' ', $short));
    }

    /** Word that fulltext cannot find as it is. */
    public static function isShort(string $word): bool
    {
        return mb_strlen($word) < self::MIN_TOKEN || in_array($word, self::STOPWORDS, true);
    }

    /**
     * Boolean mode terms of a query: every word is required, longer words match as
     * a prefix ("golf" -> "+golf*"), short ones exactly ("a4" -> "+a4qqq").
     */
    public static function booleanQuery(string $query): string
    {
        return implode(' ', array_map(
            fn (string $w) => self::isShort($w) ? '+'.$w.self::SHORT_SUFFIX : '+'.$w.'*',
            self::words($query),
        ));
    }

    /**
     * Words of a query, normalized, without duplicates (max 10).
     *
     * @return list<string>
     */
    public static function words(string $query): array
    {
        $words = array_values(array_unique(array_filter(explode(' ', self::normalize($query)), 'strlen')));

        return array_slice($words, 0, 10);
    }
}
