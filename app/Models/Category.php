<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Field::class)->withPivot(['required', 'sort'])->orderByPivot('sort');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /** Javna adresa kategorije: /oglasi/{slug}/ */
    public function url(): string
    {
        return url_slash('/oglasi/'.$this->slug);
    }

    /** URL ikonice: pun URL iz uvoza ili putanja na disku "public"; null kad je nema. */
    public function iconUrl(): ?string
    {
        $path = trim((string) $this->icon_path);
        if ($path === '') {
            return null;
        }

        return preg_match('~^https?://~i', $path) ? $path : Storage::disk('public')->url($path);
    }
}
