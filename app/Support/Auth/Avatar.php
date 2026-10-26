<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Profile photo: a 400 px square (WebP), on the "public" disk in avatars/{id}/. */
class Avatar
{
    public const SIZE = 400;

    /** Stores the image from a file on disk and returns the path; deletes the old image. */
    public static function storeFromFile(User $user, string $sourcePath): string
    {
        $binary = @file_get_contents($sourcePath);
        if ($binary === false) {
            throw new RuntimeException('Slika ne može da se pročita.');
        }

        return static::storeFromBinary($user, $binary);
    }

    public static function storeFromBinary(User $user, string $binary): string
    {
        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw new RuntimeException('Fajl nije ispravna slika.');
        }

        $w = imagesx($source);
        $h = imagesy($source);
        $side = min($w, $h);
        $size = min(self::SIZE, $side);

        $target = imagecreatetruecolor($size, $size);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, imagecolorallocatealpha($target, 255, 255, 255, 127));
        // Crop from the middle, then shrink.
        imagecopyresampled($target, $source, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $size, $size, $side, $side);

        ob_start();
        imagewebp($target, null, 82);
        $data = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        $path = 'avatars/'.$user->id.'/'.Str::lower(Str::random(16)).'.webp';
        Storage::disk('public')->put($path, $data);

        static::delete($user);
        $user->forceFill(['avatar_path' => $path])->save();

        return $path;
    }

    public static function delete(User $user): void
    {
        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }
    }
}
