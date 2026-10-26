<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Support\Price;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Listing extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'views', 'phone_reveals', 'wp_id'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_eur' => 'decimal:2',
            'currency' => Currency::class,
            'status' => ListingStatus::class,
            'featured_until' => 'datetime',
            'priority_until' => 'datetime',
            'top_until' => 'datetime',
            'premium_xl_until' => 'datetime',
            'premium_home_until' => 'datetime',
            'bumped_at' => 'datetime',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
        ];
    }

    protected static function booted(): void
    {
        // price_eur is always computed from the price and currency (rate from the settings).
        static::saving(function (Listing $listing) {
            if ($listing->isDirty(['price', 'currency']) || $listing->price_eur === null) {
                $listing->price_eur = $listing->price === null
                    ? null
                    : Price::toEur((float) $listing->price, $listing->currency ?? Currency::EUR);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ListingFieldValue::class);
    }

    /** Publicly visible listings. */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ListingStatus::Published);
    }

    public function isFeatured(): bool
    {
        return $this->featured_until !== null && $this->featured_until->isFuture();
    }

    public function priceLabel(): string
    {
        return Price::label($this->price === null ? null : (float) $this->price, $this->currency ?? Currency::EUR);
    }

    public function url(): string
    {
        return url('/oglas/'.$this->slug.'/');
    }

    // Admin panel

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    // Listing page module: cover image, stats, visibility

    /** First image by sort order (cards and API without loading the whole gallery). */
    public function coverImage(): HasOne
    {
        return $this->hasOne(ListingImage::class)->ofMany(['sort' => 'min', 'id' => 'min']);
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(ListingDailyStat::class);
    }

    /** Published and not past its expiry date. */
    public function isActive(): bool
    {
        return $this->status === ListingStatus::Published
            && ! $this->trashed()
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** Expired by status or by date (shown with a notice and noindex). */
    public function isExpired(): bool
    {
        return $this->status === ListingStatus::Expired
            || ($this->status === ListingStatus::Published && $this->expires_at !== null && ! $this->expires_at->isFuture());
    }

    /**
     * Since when a sold or expired listing is no longer active (null while it is active or not public).
     * After seo.inactive_gone_days its page answers 410 to visitors; renewing brings it back.
     */
    public function inactiveSince(): ?Carbon
    {
        return match (true) {
            $this->status === ListingStatus::Sold => $this->updated_at,
            $this->isExpired() => $this->expires_at ?? $this->updated_at,
            default => null,
        };
    }

    /** Active listings: published and not expired. */
    public function scopeActive(Builder $query): void
    {
        $query->where('listings.status', ListingStatus::Published)
            ->where(fn (Builder $q) => $q->whereNull('listings.expires_at')->orWhere('listings.expires_at', '>', now()));
    }

    // Payments

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Search rank of the active promotion: 3 TOP or Premium XL, 2 priority, 1 highlighted, 0 none. */
    public function promotionRank(): int
    {
        return match (true) {
            $this->top_until?->isFuture() === true, $this->isPremiumXl() => 3,
            $this->priority_until?->isFuture() === true => 2,
            $this->isFeatured() => 1,
            default => 0,
        };
    }

    /** Shown as the bigger Premium XL card in lists. */
    public function isPremiumXl(): bool
    {
        return $this->premium_xl_until?->isFuture() === true;
    }

    /** Also shown in the Premium section of the home page. */
    public function isPremiumHome(): bool
    {
        return $this->premium_home_until?->isFuture() === true;
    }
}
