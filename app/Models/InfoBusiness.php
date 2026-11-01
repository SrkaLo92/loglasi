<?php

namespace App\Models;

use App\Support\Info\InfoUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A business in the info portal's directory. Listed from public business data, so a firm can be
 * found before it has an account; it can claim the entry (link to its firm profile) or ask for removal.
 */
class InfoBusiness extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'invited_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InfoBusiness $business) {
            if (blank($business->slug)) {
                $business->slug = self::uniqueSlug($business->name, $business->id);
            }
        });
    }

    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(InfoBusinessRequest::class);
    }

    public function scopeListed(Builder $query): Builder
    {
        return $query->where('status', 'listed');
    }

    public function url(): string
    {
        return InfoUrl::to('firme/'.$this->slug);
    }

    /** The firm's profile on the classifieds site, when the entry is linked to a firm account. */
    public function profileUrl(): ?string
    {
        $slug = $this->firm?->user?->slug;

        return $slug ? InfoUrl::main('korisnik/'.$slug) : null;
    }

    /** @return list<string> */
    public function phones(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[;,]/', (string) $this->phone) ?: [])));
    }

    public static function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = trim(Str::limit(Str::slug($name, '-', 'sr'), 120, ''), '-') ?: 'firma';
        $slug = $base;
        for ($i = 2; self::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
