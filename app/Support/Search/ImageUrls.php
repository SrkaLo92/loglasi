<?php

namespace App\Support\Search;

use App\Models\ListingImage;
use Illuminate\Support\Facades\Storage;

/**
 * URLs of a listing image by size, from listing_images.variants or the original.
 * Variants look like {"sm": {"webp": "...", "jpg": "..."}, "md": ..., "lg": ...};
 * a missing size falls back to the next larger one and finally to the original.
 */
class ImageUrls
{
    private const SIZES = [
        'thumb' => ['sm', 'thumb', 'md', 'medium', 'lg', 'large'],
        'medium' => ['md', 'medium', 'lg', 'large'],
        'large' => ['lg', 'large', 'xl'],
    ];

    /** URL for a size (thumb ~400 px, medium ~800 px, large ~1600 px). */
    public static function url(ListingImage $image, string $size = 'medium', string $format = 'webp'): string
    {
        $variants = is_array($image->variants) ? $image->variants : [];

        foreach (self::SIZES[$size] ?? self::SIZES['medium'] as $key) {
            $variant = $variants[$key] ?? null;
            if (is_string($variant) && $variant !== '') {
                return self::toUrl($variant);
            }
            if (is_array($variant)) {
                $path = $variant[$format] ?? $variant['jpg'] ?? $variant['jpeg'] ?? $variant['webp'] ?? reset($variant);
                if (is_string($path) && $path !== '') {
                    return self::toUrl($path);
                }
            }
        }

        return self::toUrl((string) $image->path);
    }

    /** JPEG (or original) URL for browsers without WebP; null when there is no such variant. */
    public static function fallback(ListingImage $image, string $size = 'medium'): ?string
    {
        $variants = is_array($image->variants) ? $image->variants : [];
        foreach (self::SIZES[$size] ?? self::SIZES['medium'] as $key) {
            $path = $variants[$key]['jpg'] ?? $variants[$key]['jpeg'] ?? null;
            if (is_string($path) && $path !== '') {
                return self::toUrl($path);
            }
        }

        return null;
    }

    private static function toUrl(string $path): string
    {
        return preg_match('~^https?://~i', $path) ? $path : Storage::disk('public')->url(ltrim($path, '/'));
    }
}
