<?php

namespace App\Http\Api;

use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Services\ListingFields;
use App\Services\ListingImages;
use DateTimeInterface;

/**
 * ListingCard and Listing shapes from the API contract.
 * Minimal version for the posting module; the listing module owns the full one
 * (seller details, favorites) and replaces this file.
 */
class ListingFormatter
{
    /** Relations card() needs, for eager loading. */
    public const CARD_RELATIONS = ['category', 'location', 'images', 'user'];

    public static function card(Listing $listing): array
    {
        $listing->loadMissing(self::CARD_RELATIONS);
        $cover = $listing->images->first();

        return [
            'id' => $listing->id,
            'title' => $listing->title,
            'price' => $listing->price === null ? null : (int) round((float) $listing->price),
            'currency' => ($listing->currency ?? Currency::EUR)->value,
            'price_label' => $listing->priceLabel(),
            'location' => $listing->location ? CatalogFormatter::locationRef($listing->location) : null,
            'category' => $listing->category ? CatalogFormatter::categoryRef($listing->category) : null,
            'image' => $cover ? app(ListingImages::class)->toApi($cover) : null,
            'featured' => $listing->isFeatured(),
            'seller_type' => $listing->user?->isFirm() ? 'firm' : 'private',
            'status' => static::status($listing),
            'published_at' => static::date($listing->published_at),
            'url' => $listing->url(),
        ];
    }

    public static function detail(Listing $listing, ?User $viewer = null): array
    {
        $listing->loadMissing([...self::CARD_RELATIONS, 'fieldValues', 'user.firm']);
        $fields = app(ListingFields::class);
        $images = app(ListingImages::class);
        $user = $listing->user;

        return static::card($listing) + [
            'description' => (string) $listing->description,
            'images' => $listing->images->map(fn ($image) => $images->toApi($image))->values()->all(),
            'fields' => $fields->values($listing),
            'condition' => $fields->condition($listing),
            'seller' => $user ? [
                'id' => $user->id,
                'name' => $user->isFirm() && $user->firm ? $user->firm->name : $user->name,
                'type' => $user->isFirm() ? 'firm' : 'private',
                'firm_id' => $user->isFirm() ? $user->firm?->id : null,
                'logo' => null,
                'phone_verified' => $user->isPhoneVerified(),
                'member_since' => static::date($user->created_at),
                'active_listings' => $user->listings()->where('status', ListingStatus::Published)->where('expires_at', '>', now())->count(),
            ] : null,
            'views' => (int) $listing->views,
            'is_favorite' => $viewer ? $viewer->favorites()->whereKey($listing->id)->exists() : false,
            'is_mine' => $viewer !== null && $viewer->id === $listing->user_id,
            'expires_at' => static::date($listing->expires_at),
            'reject_reason' => $listing->status === ListingStatus::Rejected ? $listing->rejection_reason : null,
            'lat' => $listing->lat,
            'lng' => $listing->lng,
        ];
    }

    /** Status from the contract: pending | active | rejected | expired | sold (a draft counts as pending). */
    public static function status(Listing $listing): string
    {
        return match ($listing->status) {
            ListingStatus::Published => $listing->expires_at !== null && $listing->expires_at->isPast() ? 'expired' : 'active',
            ListingStatus::Rejected => 'rejected',
            ListingStatus::Expired => 'expired',
            ListingStatus::Sold => 'sold',
            default => 'pending',
        };
    }

    public static function date(?DateTimeInterface $date): ?string
    {
        return $date ? $date->copy()->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }
}
