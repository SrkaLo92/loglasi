<?php

namespace App\Http\Api;

use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Services\ListingFields;
use App\Support\Listing\Seller;
use App\Support\Listing\WorkingHours;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Contract shapes for listings: ListingCard, Listing, Image, Seller, Firm.
 * Port of class-loglasi-api-formatter.php. Eager load CARD_RELATIONS (lists) or
 * DETAIL_RELATIONS (detail) first, so formatting runs no queries per listing.
 */
class ListingFormatter
{
    public const CARD_RELATIONS = ['category', 'location', 'user', 'coverImage'];

    public const DETAIL_RELATIONS = ['category', 'location', 'user.firm', 'images', 'fieldValues'];

    /** ListingCard */
    public static function card(Listing $listing): array
    {
        $listing->loadMissing(['category', 'location', 'user']);
        $image = static::cover($listing);
        $price = $listing->price === null ? null : (int) round((float) $listing->price);

        return [
            'id' => (int) $listing->id,
            'title' => static::plain($listing->title),
            'price' => $price,
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

    /**
     * Listing (detail). Without a viewer is_mine/is_favorite are false and reject_reason is null.
     */
    public static function detail(Listing $listing, ?User $viewer = null): array
    {
        $listing->loadMissing(static::DETAIL_RELATIONS);
        $fields = app(ListingFields::class);
        $mine = $viewer !== null && $viewer->id === $listing->user_id;

        return static::card($listing) + [
            'description' => (string) $listing->description,
            'images' => $listing->images->map(fn (ListingImage $image) => static::image($image))->filter()->values()->all(),
            'fields' => array_map(fn (array $f) => [
                'key' => $f['key'],
                'label' => $f['label'],
                'value' => (string) $f['value'],
                'display' => (string) $f['display'],
            ], $fields->values($listing)),
            'condition' => $fields->condition($listing),
            'seller' => $listing->user ? static::seller($listing->user) : null,
            'views' => (int) $listing->views,
            'is_favorite' => $viewer !== null && DB::table('favorites')->where('user_id', $viewer->id)->where('listing_id', $listing->id)->exists(),
            'is_mine' => $mine,
            'expires_at' => static::date($listing->expires_at),
            'reject_reason' => ($mine || $viewer?->isStaff()) && $listing->status === ListingStatus::Rejected ? $listing->rejection_reason : null,
            'lat' => $listing->lat,
            'lng' => $listing->lng,
        ];
    }

    /** Image: thumb ~400 px, medium ~800 px, large ~1600 px (variants when present, else the original). */
    public static function image(ListingImage $image): ?array
    {
        $large = $image->sizeUrl('lg');
        if ($large === null) {
            return null;
        }

        return [
            'id' => (int) $image->id,
            'thumb' => $image->sizeUrl('sm') ?? $large,
            'medium' => $image->sizeUrl('md') ?? $large,
            'large' => $large,
            'width' => (int) $image->width,
            'height' => (int) $image->height,
        ];
    }

    /** Image shape for a single file (firm logo, avatar). */
    public static function singleImage(int $id, ?string $url, int $width = 0, int $height = 0): ?array
    {
        return $url ? ['id' => $id, 'thumb' => $url, 'medium' => $url, 'large' => $url, 'width' => $width, 'height' => $height] : null;
    }

    /** Seller. No email and no phone (the phone goes through /contact). */
    public static function seller(User $user, ?int $activeListings = null): array
    {
        $user->loadMissing('firm');
        $firm = $user->isFirm() ? $user->firm : null;

        return [
            'id' => (int) $user->id,
            'name' => static::plain($firm?->name ?: $user->name),
            'type' => $user->isFirm() ? 'firm' : 'private',
            'firm_id' => $firm?->id,
            'logo' => $firm?->logo_path
                ? static::singleImage((int) $firm->id, $firm->logoUrl())
                : static::singleImage((int) $user->id, $user->avatarUrl(), 400, 400),
            'phone_verified' => $user->isPhoneVerified(),
            'member_since' => static::date($user->created_at),
            'active_listings' => $activeListings ?? Seller::activeCount($user->id),
        ];
    }

    /** Firm (public profile). */
    public static function firm(Firm $firm): array
    {
        $firm->loadMissing(['user', 'location']);
        $address = trim(implode(', ', array_filter([$firm->address, $firm->location?->name])));

        return [
            'id' => (int) $firm->id,
            'name' => static::plain($firm->name),
            'about' => (string) $firm->description,
            'logo' => static::singleImage((int) $firm->id, $firm->logoUrl()),
            'address' => $address,
            'website' => (string) $firm->website,
            'working_hours' => WorkingHours::text(WorkingHours::days($firm->working_hours)),
            'phone_verified' => (bool) $firm->user?->isPhoneVerified(),
            'package' => $firm->hasBiznis() ? 'Biznis' : 'Bez paketa',
            'package_expires_at' => $firm->hasBiznis() ? static::date($firm->biznis_until) : null,
            'active_listings' => Seller::activeCount((int) $firm->user_id),
            'member_since' => static::date($firm->user?->created_at ?? $firm->created_at),
        ];
    }

    /** Contract status: pending, active, rejected, expired, sold (draft counts as pending). */
    public static function status(Listing $listing): string
    {
        return match (true) {
            $listing->status === ListingStatus::Sold => 'sold',
            $listing->isExpired() => 'expired',
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

    private static function cover(Listing $listing): ?ListingImage
    {
        if ($listing->relationLoaded('images')) {
            return $listing->images->first();
        }

        return $listing->loadMissing('coverImage')->coverImage;
    }

    private static function plain(?string $text): string
    {
        return html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
