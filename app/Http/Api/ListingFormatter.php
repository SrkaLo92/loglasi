<?php

namespace App\Http\Api;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\Search\ImageUrls;

/**
 * ListingCard shape of the API contract.
 *
 * Minimal version for GET /listings; the listing module owns this class and
 * replaces it with the full one (Listing, Image, Seller) when it is merged.
 * Expects the relations of ListingSearch::cardRelations().
 */
class ListingFormatter
{
    public static function card(Listing $listing): array
    {
        $image = $listing->relationLoaded('coverImage') ? $listing->coverImage : $listing->images()->first();

        return [
            'id' => (int) $listing->id,
            'title' => html_entity_decode($listing->title, ENT_QUOTES, 'UTF-8'),
            'price' => $listing->price === null ? null : (int) round((float) $listing->price),
            'currency' => $listing->currency->value,
            'price_label' => $listing->priceLabel(),
            'location' => $listing->location ? CatalogFormatter::locationRef($listing->location) : null,
            'category' => $listing->category ? CatalogFormatter::categoryRef($listing->category) : null,
            'image' => $image ? self::image($image) : null,
            'featured' => $listing->isFeatured(),
            'seller_type' => $listing->user?->isFirm() ? 'firm' : 'private',
            'status' => self::status($listing),
            'published_at' => ($listing->published_at ?? $listing->created_at)?->utc()->toIso8601ZuluString(),
            'url' => url_slash('/oglas/'.$listing->slug),
        ];
    }

    public static function image(ListingImage $image): array
    {
        return [
            'id' => (int) $image->id,
            'thumb' => ImageUrls::url($image, 'thumb'),
            'medium' => ImageUrls::url($image, 'medium'),
            'large' => ImageUrls::url($image, 'large'),
            'width' => $image->width === null ? null : (int) $image->width,
            'height' => $image->height === null ? null : (int) $image->height,
        ];
    }

    private static function status(Listing $listing): string
    {
        return match ($listing->status) {
            ListingStatus::Published => 'active',
            ListingStatus::Expired => 'expired',
            ListingStatus::Rejected => 'rejected',
            default => 'pending',
        };
    }
}
