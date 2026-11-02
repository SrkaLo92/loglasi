<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInfoCity;
use App\Support\Info\InfoUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A paid ad on the info portal. One firm per slot at a time (no rotation); clicks are counted on the way out. */
class InfoAd extends Model
{
    use BelongsToInfoCity;

    public const SLOTS = [
        'today_sponsor' => 'Sponzor „Danas u Loznici“',
        'article_bottom' => 'Baner ispod teksta vesti',
        'home_middle' => 'Baner na naslovnoj',
        'category_top' => 'Vrh kategorije u imeniku',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(InfoBusiness::class, 'info_business_id');
    }

    public function scopeRunning(Builder $query): Builder
    {
        $today = now('Europe/Belgrade')->toDateString();

        return $query->where('active', true)->whereDate('starts_on', '<=', $today)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    /** Where a click goes: the ad's own link, else the business profile. */
    public function target(): ?string
    {
        return $this->link_url ?: $this->business?->url();
    }

    public function clickUrl(): string
    {
        return InfoUrl::to('klik/'.$this->id);
    }
}
