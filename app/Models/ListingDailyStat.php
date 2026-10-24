<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per listing per day (Europe/Belgrade): views, phone reveals, messages, favorites. */
class ListingDailyStat extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
