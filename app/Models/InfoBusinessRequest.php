<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A firm asks to take over, correct or remove its directory entry. */
class InfoBusinessRequest extends Model
{
    public const KINDS = [
        'claim' => 'Preuzimanje profila',
        'correct' => 'Ispravka podataka',
        'remove' => 'Uklanjanje',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(InfoBusiness::class, 'info_business_id');
    }
}
