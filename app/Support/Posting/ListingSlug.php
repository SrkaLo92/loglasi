<?php

namespace App\Support\Posting;

use App\Models\Listing;
use Illuminate\Support\Str;

/**
 * Listing slug for /oglas/{slug}/, made like WordPress sanitize_title() with the sr_RS locale:
 * č ć → c, š → s, ž → z, đ → dj, Cyrillic → Latin, dots become dashes.
 * Unique among all listings, deleted ones included (their URLs answer 410).
 * The slug is set once, when the listing is submitted, and never changes after that.
 */
class ListingSlug
{
    public const MAX_LENGTH = 180;

    /** Drafts get a placeholder slug until they are submitted. */
    public const DRAFT_PREFIX = 'draft-';

    public static function base(string $title): string
    {
        $title = str_replace(['.', '@', '&', '_', '/'], ' ', $title);
        $slug = Str::slug($title, '-', 'sr');

        if (strlen($slug) > self::MAX_LENGTH) {
            $cut = substr($slug, 0, self::MAX_LENGTH);
            $slug = str_contains($cut, '-') ? substr($cut, 0, (int) strrpos($cut, '-')) : $cut;
        }

        return trim($slug, '-') !== '' ? trim($slug, '-') : 'oglas';
    }

    public static function unique(string $title, ?int $ignoreId = null): string
    {
        $base = static::base($title);

        $taken = Listing::withTrashed()
            ->where(fn ($q) => $q->where('slug', $base)->orWhere('slug', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $base).'-%'))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->pluck('slug')
            ->flip();

        if (! $taken->has($base)) {
            return $base;
        }

        for ($n = 2; ; $n++) {
            if (! $taken->has($base.'-'.$n)) {
                return $base.'-'.$n;
            }
        }
    }

    public static function draft(): string
    {
        return self::DRAFT_PREFIX.Str::lower(Str::random(16));
    }

    public static function isDraft(?string $slug): bool
    {
        return $slug === null || str_starts_with($slug, self::DRAFT_PREFIX);
    }
}
