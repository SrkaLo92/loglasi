<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ListingImage extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['variants' => 'array'];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    // Listing page module: URLs of the original and size variants

    /** Variant size => approximate width in px (variants JSON: {"sm": {"webp": path, "jpg": path}, ...}). */
    public const SIZES = ['sm' => 400, 'md' => 800, 'lg' => 1600];

    /** URL of a path on the "public" disk, or the path itself when it is already a URL. */
    public static function urlFor(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        return preg_match('#^https?://#i', $path) ? $path : Storage::disk('public')->url($path);
    }

    public function originalUrl(): ?string
    {
        return static::urlFor($this->path);
    }

    /** Variant URL for a size and format (webp, jpg); null when the variant does not exist. */
    public function variantUrl(string $size, string $format = 'jpg'): ?string
    {
        $variant = $this->variants[$size] ?? null;
        if (! is_array($variant)) {
            return null;
        }
        $path = $variant[$format] ?? ($format === 'jpg' ? ($variant['jpeg'] ?? null) : null);

        return is_string($path) ? static::urlFor($path) : null;
    }

    /** Best URL for a size: WebP variant, JPEG variant, then the original. */
    public function sizeUrl(string $size): ?string
    {
        return $this->variantUrl($size, 'webp') ?? $this->variantUrl($size, 'jpg') ?? $this->originalUrl();
    }

    /** srcset for one format ("url 400w, url 800w"), empty when there are no variants. */
    public function srcset(string $format): string
    {
        $parts = [];
        foreach (static::SIZES as $size => $width) {
            if ($url = $this->variantUrl($size, $format)) {
                $parts[] = $url.' '.$this->variantWidth($size, $width).'w';
            }
        }

        return implode(', ', $parts);
    }

    /** Real variant width when known (never wider than the original). */
    private function variantWidth(string $size, int $default): int
    {
        $width = (int) ($this->variants[$size]['width'] ?? 0) ?: $default;

        return $this->width ? min($width, (int) $this->width) : $width;
    }
}
