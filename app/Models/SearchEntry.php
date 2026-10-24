<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Row of the search index (listing_search): one per active listing, same id as the listing.
 * Written only by App\Services\SearchIndex.
 */
class SearchEntry extends Model
{
    protected $table = 'listing_search';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_firm' => 'boolean',
            'no_price' => 'boolean',
            'featured' => 'boolean',
            'featured_until' => 'datetime',
            'sort_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'id');
    }
}
