<?php

namespace App\Services\Messages;

use App\Models\ListingImage;
use Illuminate\Support\Facades\Storage;

/** URL of a listing image variant (sm, md, lg); falls back to the original file. Imported rows may hold full URLs. */
class ListingThumb
{
    public static function url(?ListingImage $image, string $size = 'sm'): ?string
    {
        if ($image === null) {
            return null;
        }

        $variants = (array) $image->variants;
        $path = $variants[$size]['webp'] ?? $variants[$size]['jpg'] ?? $variants[$size]['jpeg'] ?? (is_string($variants[$size] ?? null) ? $variants[$size] : null) ?? $image->path;

        if (! is_string($path) || $path === '') {
            return null;
        }

        return preg_match('#^https?://#i', $path) ? $path : Storage::disk('public')->url($path);
    }
}
