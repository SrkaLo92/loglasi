<?php

namespace App\Support\Firms;

use App\Models\Firm;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Firm logo and cover image, stored as WebP on the "public" disk in firms/{firm id}/.
 * Logo: fits inside config('firms.logo_size') (aspect kept, transparency kept).
 * Cover: center crop to config('firms.cover_size'), never upscaled.
 */
class FirmImages
{
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return preg_match('#^https?://#i', $path) ? $path : Storage::disk('public')->url($path);
    }

    public static function storeLogo(Firm $firm, string $sourcePath): string
    {
        $source = static::load($sourcePath);
        $max = (int) config('firms.logo_size', 400);
        [$w, $h] = [imagesx($source), imagesy($source)];
        $scale = min(1, $max / max($w, $h));
        $tw = max(1, (int) round($w * $scale));
        $th = max(1, (int) round($h * $scale));

        $target = static::canvas($tw, $th);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $tw, $th, $w, $h);

        return static::save($firm, 'logo_path', 'logo', $target, $source);
    }

    public static function storeCover(Firm $firm, string $sourcePath): string
    {
        $source = static::load($sourcePath);
        [$maxW, $maxH] = config('firms.cover_size', [1600, 500]);
        [$w, $h] = [imagesx($source), imagesy($source)];
        $ratio = $maxW / $maxH;

        // Largest centered area with the target ratio.
        if ($w / $h > $ratio) {
            $cropH = $h;
            $cropW = (int) round($h * $ratio);
        } else {
            $cropW = $w;
            $cropH = (int) round($w / $ratio);
        }
        $tw = min($maxW, $cropW);
        $th = max(1, (int) round($tw / $ratio));

        $target = static::canvas($tw, $th);
        imagecopyresampled($target, $source, 0, 0, (int) (($w - $cropW) / 2), (int) (($h - $cropH) / 2), $tw, $th, $cropW, $cropH);

        return static::save($firm, 'cover_path', 'cover', $target, $source);
    }

    public static function delete(Firm $firm, string $column): void
    {
        $path = $firm->getAttribute($column);
        if ($path) {
            if (! preg_match('#^https?://#i', $path)) {
                Storage::disk('public')->delete($path);
            }
            $firm->forceFill([$column => null])->save();
        }
    }

    private static function load(string $path): \GdImage
    {
        $binary = @file_get_contents($path);
        $image = $binary === false ? false : @imagecreatefromstring($binary);
        if ($image === false) {
            throw new RuntimeException('Fajl nije ispravna slika.');
        }

        return $image;
    }

    private static function canvas(int $w, int $h): \GdImage
    {
        $target = imagecreatetruecolor($w, $h);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 255, 255, 255, 127));

        return $target;
    }

    private static function save(Firm $firm, string $column, string $name, \GdImage $target, \GdImage $source): string
    {
        ob_start();
        imagewebp($target, null, 84);
        $data = (string) ob_get_clean();
        imagedestroy($target);
        imagedestroy($source);

        $path = 'firms/'.$firm->id.'/'.$name.'-'.Str::lower(Str::random(12)).'.webp';
        Storage::disk('public')->put($path, $data);

        $old = $firm->getAttribute($column);
        $firm->forceFill([$column => $path])->save();

        if ($old && $old !== $path && ! preg_match('#^https?://#i', $old)) {
            Storage::disk('public')->delete($old);
        }

        return $path;
    }
}
