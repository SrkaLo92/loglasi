<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A buyer's rating of a seller (1-5 stars, comment, the seller's reply). See App\Services\Ratings\SellerRatings. */
class SellerRating extends Model
{
    public const VISIBLE = 'visible';

    public const HIDDEN = 'hidden';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'replied_at' => 'datetime',
            'reported_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id')->withTrashed();
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_id')->withTrashed();
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class)->withTrashed();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @param  Builder<SellerRating>  $query */
    public function scopeVisible(Builder $query): void
    {
        $query->where('status', self::VISIBLE);
    }

    public function isVisible(): bool
    {
        return $this->status === self::VISIBLE;
    }
}
