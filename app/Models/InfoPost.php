<?php

namespace App\Models;

use App\Support\Info\InfoUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A post on the info portal: a news summary (with a link to its source), an event, a local story,
 * a guide or a notice. Nothing is public until an editor publishes it.
 */
class InfoPost extends Model
{
    public const TYPES = [
        'news' => 'Vest',
        'event' => 'Događaj',
        'story' => 'Priča',
        'guide' => 'Vodič',
        'notice' => 'Obaveštenje',
    ];

    /** Public path segment of each type. */
    public const SECTIONS = [
        'news' => 'vesti',
        'event' => 'desavanja',
        'story' => 'price',
        'guide' => 'vodici',
        'notice' => 'vesti',
    ];

    public const STATUSES = [
        'pending' => 'Na čekanju',
        'published' => 'Objavljeno',
        'rejected' => 'Odbijeno',
    ];

    public const ORIGINS = [
        'manual' => 'Urednik',
        'automation' => 'Automatika',
        'citizen' => 'Građanin',
    ];

    protected $guarded = ['id', 'reviewed_by'];

    protected function casts(): array
    {
        return [
            'event_starts_at' => 'datetime',
            'event_ends_at' => 'datetime',
            'published_at' => 'datetime',
            'sponsored' => 'boolean',
            'featured' => 'boolean',
            'needs_check' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (InfoPost $post) {
            if (blank($post->slug)) {
                $post->slug = self::uniqueSlug($post->title, $post->id);
            }
            $post->source_hash = filled($post->source_url) ? sha1(self::normalizeUrl($post->source_url)) : null;
            if ($post->status === 'published' && ! $post->published_at) {
                $post->published_at = now();
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }

    /** News list: news and notices together. */
    public function scopeOfSection(Builder $query, string $section): Builder
    {
        return $query->whereIn('type', array_keys(array_filter(self::SECTIONS, fn ($s) => $s === $section)));
    }

    public function scopeUpcomingEvents(Builder $query): Builder
    {
        return $query->published()->where('type', 'event')
            ->where(fn ($q) => $q->where('event_starts_at', '>=', now()->startOfDay())
                ->orWhere('event_ends_at', '>=', now()));
    }

    public function section(): string
    {
        return self::SECTIONS[$this->type] ?? 'vesti';
    }

    public function url(): string
    {
        return InfoUrl::to($this->section().'/'.$this->slug);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function topicLabel(): ?string
    {
        return $this->topic ? (config('info.topics')[$this->topic] ?? null) : null;
    }

    public function imageUrl(): ?string
    {
        return ListingImage::urlFor($this->image_path);
    }

    /** A summary of another outlet's article: its full text lives at the source, so the page is not indexed. */
    public function isSourceSummary(): bool
    {
        return in_array($this->type, ['news', 'notice'], true) && filled($this->source_url) && blank($this->body);
    }

    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('/#.*$/', '', $url) ?? $url;
        $url = preg_replace('/[?&]utm_[^&]*/', '', $url) ?? $url;

        return rtrim(Str::lower($url), '/?&');
    }

    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::limit(Str::slug(str_replace(['.', '@', '&', '_', '/'], ' ', $title), '-', 'sr'), 120, '');
        $base = trim($base, '-') ?: 'objava';
        $slug = $base;
        for ($i = 2; self::query()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
