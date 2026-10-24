<?php

namespace App\Models;

use App\Support\Notifications\SavedSearchQuery;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavedSearch extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['query' => 'array', 'notify' => 'boolean', 'last_notified_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Notifications: alerts for new matching listings

    public function matches(): HasMany
    {
        return $this->hasMany(SavedSearchMatch::class);
    }

    /** Results page for this search: /oglasi/?keyword=...&kategorija=... */
    public function url(): string
    {
        return SavedSearchQuery::url((array) $this->query);
    }
}
