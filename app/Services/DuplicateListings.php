<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Support\Str;

/**
 * Finds a listing the same user already posted: a very similar title in the same
 * category, or the same picture, within the last 30 days.
 */
class DuplicateListings
{
    public const DAYS = 30;

    /** similar_text() percentage from which two titles count as the same. */
    public const TITLE_SIMILARITY = 85.0;

    /** Hashes of plain one-color images, which match too easily. */
    private const IGNORED_PHASHES = ['0000000000000000', 'ffffffffffffffff'];

    private const STATUSES = [ListingStatus::Published, ListingStatus::Pending, ListingStatus::Expired];

    public function similarTitle(Listing $listing, ?string $title = null, ?int $categoryId = null): ?Listing
    {
        $title = static::normalize($title ?? $listing->title);
        if ($title === '') {
            return null;
        }

        $candidates = Listing::query()
            ->where('user_id', $listing->user_id)
            ->where('category_id', $categoryId ?? $listing->category_id)
            ->whereIn('status', self::STATUSES)
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->when($listing->exists, fn ($q) => $q->whereKeyNot($listing->id))
            ->latest('id')
            ->limit(200)
            ->get(['id', 'title', 'slug', 'status', 'user_id', 'expires_at']);

        return $candidates->first(fn (Listing $other) => static::similar($title, static::normalize($other->title)));
    }

    public function sameImage(Listing $listing): ?Listing
    {
        return $this->sameImageAs((int) $listing->user_id, $listing->images()->get(['id', 'hash', 'phash']), $listing->exists ? $listing->id : null);
    }

    /**
     * Another listing of the user (not $exceptListingId) with one of these images.
     *
     * @param  iterable<ListingImage>  $images
     */
    public function sameImageAs(int $userId, iterable $images, ?int $exceptListingId = null): ?Listing
    {
        $images = collect($images);
        $hashes = $images->pluck('hash')->filter()->values()->all();
        $phashes = $images->pluck('phash')->filter()->reject(fn ($h) => in_array($h, self::IGNORED_PHASHES, true))->values()->all();
        if ($hashes === [] && $phashes === []) {
            return null;
        }

        $listingId = ListingImage::query()
            ->join('listings', 'listings.id', '=', 'listing_images.listing_id')
            ->where('listings.user_id', $userId)
            ->when($exceptListingId, fn ($q) => $q->where('listings.id', '!=', $exceptListingId))
            ->whereNull('listings.deleted_at')
            ->whereIn('listings.status', array_map(fn ($s) => $s->value, self::STATUSES))
            ->where('listings.created_at', '>=', now()->subDays(self::DAYS))
            ->where(function ($q) use ($hashes, $phashes) {
                $q->whereIn('listing_images.hash', $hashes ?: ['-']);
                if ($phashes !== []) {
                    $q->orWhereIn('listing_images.phash', $phashes);
                }
            })
            ->value('listings.id');

        return $listingId ? Listing::query()->find($listingId) : null;
    }

    /** Lowercase Latin letters and digits separated by single spaces. */
    public static function normalize(?string $title): string
    {
        return trim(str_replace('-', ' ', Str::slug((string) $title, '-', 'sr')));
    }

    public static function similar(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        similar_text($a, $b, $percent);

        return $percent >= self::TITLE_SIMILARITY;
    }
}
