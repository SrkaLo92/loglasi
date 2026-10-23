<?php

namespace App\Http\Api;

use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Services\Messages\ListingThumb;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Minimal ListingCard shape from the contract. The listing module owns the full formatter;
 * this keeps the same class, method and output so the full version replaces it as is.
 * Eager load CARD_RELATIONS for lists.
 */
class ListingFormatter
{
    public const CARD_RELATIONS = ['category', 'location', 'user', 'images'];

    /** ListingCard */
    public static function card(Listing $listing): array
    {
        $listing->loadMissing(['category', 'location', 'user', 'images']);
        $image = $listing->images->first();

        return [
            'id' => (int) $listing->id,
            'title' => html_entity_decode(strip_tags((string) $listing->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'price' => $listing->price === null ? null : (int) round((float) $listing->price),
            'currency' => ($listing->currency ?? Currency::EUR)->value,
            'price_label' => $listing->priceLabel(),
            'location' => $listing->location ? CatalogFormatter::locationRef($listing->location) : null,
            'category' => $listing->category ? CatalogFormatter::categoryRef($listing->category) : null,
            'image' => $image ? static::image($image) : null,
            'featured' => $listing->isFeatured(),
            'seller_type' => $listing->user?->isFirm() ? 'firm' : 'private',
            'status' => static::status($listing),
            'published_at' => static::date($listing->published_at ?? $listing->created_at),
            'url' => url_slash('oglas/'.$listing->slug),
        ];
    }

    public static function image(ListingImage $image): ?array
    {
        $large = ListingThumb::url($image, 'lg');
        if ($large === null) {
            return null;
        }

        return [
            'id' => (int) $image->id,
            'thumb' => ListingThumb::url($image, 'sm') ?? $large,
            'medium' => ListingThumb::url($image, 'md') ?? $large,
            'large' => $large,
            'width' => (int) $image->width,
            'height' => (int) $image->height,
        ];
    }

    public static function status(Listing $listing): string
    {
        $expired = $listing->status === ListingStatus::Expired
            || ($listing->status === ListingStatus::Published && $listing->expires_at !== null && $listing->expires_at->isPast());

        return match (true) {
            $expired => 'expired',
            $listing->status === ListingStatus::Published => 'active',
            $listing->status === ListingStatus::Rejected => 'rejected',
            default => 'pending',
        };
    }

    /** ISO 8601 in UTC: 2026-10-23T18:30:00Z */
    public static function date(?DateTimeInterface $date): ?string
    {
        return $date ? CarbonImmutable::instance($date)->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }
}
