<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Promotions included in a firm's package for one calendar month, and how many were used. */
class FirmMonthlyCredit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['month' => 'date'];
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }
}
