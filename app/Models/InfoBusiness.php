<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInfoCity;
use App\Support\Firms\FirmPackage;
use App\Support\Info\InfoUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A business in the info portal's directory. Listed from public business data, so a firm can be
 * found before it has an account; it can claim the entry (link to its firm profile) or ask for removal.
 */
class InfoBusiness extends Model
{
    use BelongsToInfoCity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'featured_until' => 'datetime',
            'gallery' => 'array',
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

    /** Featured first (paid period or the flag), then entries linked to a firm account, then by name. */
    public function scopeDirectoryOrder(Builder $query): Builder
    {
        return $query->orderByRaw('(featured = 1 or (featured_until is not null and featured_until > ?)) desc', [now()])
            ->orderByRaw('firm_id is null')->orderBy('name');
    }

    /** "Istaknuta firma": the flag, a paid period, or a Pro/Max package on loglasi.rs (included there). */
    public function isFeatured(): bool
    {
        if ($this->featured || ($this->featured_until && $this->featured_until->isFuture())) {
            return true;
        }

        return $this->firm_id && in_array(FirmPackage::code($this->firm), ['pro', 'max'], true);
    }

    public function initials(): string
    {
        $words = array_values(array_filter(preg_split('/\s+/u', $this->name) ?: [], fn ($w) => preg_match('/^\p{Lu}/u', $w)));

        return mb_strtoupper(implode('', array_map(fn ($w) => mb_substr($w, 0, 1), array_slice($words, 0, 2))) ?: mb_substr($this->name, 0, 2));
    }

    /** @return list<string> gallery photos, then the linked firm's cover */
    public function photoUrls(): array
    {
        $urls = array_map(fn ($path) => Storage::disk('public')->url($path), array_values($this->gallery ?? []));
        if ($cover = $this->firm?->coverUrl()) {
            $urls[] = $cover;
        }

        return array_slice($urls, 0, 6);
    }

    /** @return list<string> opening hours, one line per entry ("Pon–pet 08–17") */
    public function hoursLines(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $this->opening_hours) ?: [])));
    }

    public function viberNumber(): ?string
    {
        $raw = $this->viber ?: ($this->phones()[0] ?? null);
        $digits = preg_replace('/\D+/', '', (string) $raw);
        if ($digits === '') {
            return null;
        }

        return str_starts_with($digits, '381') ? $digits : '381'.ltrim($digits, '0');
    }

    public function mapsUrl(): ?string
    {
        $query = trim(($this->address ?? '').' '.($this->place && ! str_contains((string) $this->address, (string) $this->place) ? $this->place : ''));

        return $query !== '' ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query.', Srbija') : null;
    }

    /** The linked firm's active listings on loglasi.rs. */
    public function listings(int $limit = 6): Collection
    {
        if (! $this->firm_id || ! $this->firm) {
            return collect();
        }

        return $this->firm->listings()->active()->with(['coverImage', 'category'])->latest('published_at')->limit($limit)->get();
    }

    public function listingsCount(): int
    {
        return $this->firm_id && $this->firm ? $this->firm->listings()->active()->count() : 0;
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
