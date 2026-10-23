<?php

namespace App\Http\Api;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Services\ListingFields;
use Illuminate\Support\Facades\Storage;

/**
 * ListingCard and Listing shapes from the API contract.
 * Minimal version from the admin module; the listings module owns the full one.
 */
class ListingFormatter
{
    public const STATUS = [
        'pending' => 'pending',
        'published' => 'active',
        'rejected' => 'rejected',
        'expired' => 'expired',
        'draft' => 'pending',
    ];

    /** ListingCard. Load user, category, location and images first. */
    public static function card(Listing $listing): array
    {
        $image = $listing->relationLoaded('images') ? $listing->images->first() : $listing->images()->first();

        return [
            'id' => (int) $listing->id,
            'title' => $listing->title,
            'price' => $listing->price === null ? null : (int) round((float) $listing->price),
            'currency' => $listing->currency?->value ?? 'EUR',
            'price_label' => $listing->priceLabel(),
            'location' => $listing->location ? CatalogFormatter::locationRef($listing->location) : null,
            'category' => $listing->category ? CatalogFormatter::categoryRef($listing->category) : null,
            'image' => $image ? self::image($image) : null,
            'featured' => $listing->isFeatured(),
            'seller_type' => $listing->user?->isFirm() ? 'firm' : 'private',
            'status' => self::STATUS[$listing->status?->value ?? 'pending'] ?? 'pending',
            'published_at' => self::date($listing->published_at),
            'url' => $listing->url(),
        ];
    }

    /** Listing (detail). */
    public static function detail(Listing $listing, ?User $viewer = null): array
    {
        $fields = app(ListingFields::class);
        $listing->loadMissing(['images', 'fieldValues', 'user.firm']);
        $user = $listing->user;

        return self::card($listing) + [
            'description' => (string) $listing->description,
            'images' => $listing->images->map(fn (ListingImage $i) => self::image($i))->values()->all(),
            'fields' => $fields->values($listing),
            'condition' => $fields->condition($listing),
            'seller' => $user ? [
                'id' => (int) $user->id,
                'name' => $user->isFirm() && $user->firm ? $user->firm->name : $user->name,
                'type' => $user->isFirm() ? 'firm' : 'private',
                'firm_id' => $user->firm?->id,
                'logo' => null,
                'phone_verified' => $user->isPhoneVerified(),
                'member_since' => self::date($user->created_at),
                'active_listings' => (int) ($user->active_listings_count ?? $user->listings()->where('status', ListingStatus::Published)->count()),
            ] : null,
            'views' => (int) $listing->views,
            'is_favorite' => false,
            'is_mine' => $viewer !== null && $viewer->id === $listing->user_id,
            'expires_at' => self::date($listing->expires_at),
            'reject_reason' => $listing->rejection_reason,
            'lat' => $listing->lat,
            'lng' => $listing->lng,
        ];
    }

    public static function image(ListingImage $image): array
    {
        $variants = (array) $image->variants;
        $url = fn (?string $path) => $path === null ? null : (preg_match('~^https?://~i', $path) ? $path : Storage::disk('public')->url($path));
        $pick = fn (string $size) => $url($variants[$size]['webp'] ?? $variants[$size]['jpg'] ?? null) ?? $url($image->path);

        return [
            'id' => (int) $image->id,
            'thumb' => $pick('sm'),
            'medium' => $pick('md'),
            'large' => $pick('lg'),
            'width' => $image->width,
            'height' => $image->height,
        ];
    }

    private static function date($date): ?string
    {
        return $date ? $date->copy()->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }
}
