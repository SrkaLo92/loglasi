<?php

namespace App\Services;

use App\Http\Api\ListingFormatter;
use App\Jobs\GenerateImageVariants;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Support\Home\HomeSections;
use App\Support\Settings;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Listing images: upload (JPEG, PNG, WebP), content check, EXIF removal with the
 * orientation applied, storage on the "public" disk under oglasi/{listing id|tmp}/,
 * variants (sm 400, md 800, lg 1600 px wide; WebP + JPEG), order, cover, delete.
 *
 * Variants are made by the queued GenerateImageVariants job; `php artisan images:variants`
 * fills in the ones that are missing (imported WordPress images).
 */
class ListingImages
{
    public const DISK = 'public';

    /** Variant name => width in px. */
    public const SIZES = ['sm' => 400, 'md' => 800, 'lg' => 1600];

    public const DEFAULT_MAX_IMAGES = 20;

    public const DEFAULT_MAX_MB = 10;

    /** Longest side of the stored original (the app already sends at most 2000 px). */
    public const MAX_EDGE = 2560;

    /** Larger images are refused before decoding (memory). */
    public const MAX_PIXELS = 50_000_000;

    public const MIN_EDGE = 100;

    private const QUALITY_JPEG = 85;

    private const QUALITY_WEBP = 80;

    public function maxImages(): int
    {
        return max(1, (int) Settings::get('listing_max_images', self::DEFAULT_MAX_IMAGES));
    }

    public function maxBytes(): int
    {
        return (int) round(max(0.01, (float) Settings::get('image_max_mb', self::DEFAULT_MAX_MB)) * 1024 * 1024);
    }

    public function maxMegabytes(): string
    {
        $mb = $this->maxBytes() / 1024 / 1024;

        return rtrim(rtrim(number_format($mb, 2, ',', ''), '0'), ',');
    }

    // ---------------------------------------------------------------- upload

    /**
     * Stores one uploaded image. Without a listing it goes to oglasi/tmp/ until
     * attach() moves it to the listing (mobile app: POST /media, then image_ids).
     *
     * @throws ValidationException under the key $field
     */
    public function upload(UploadedFile $file, User $user, ?Listing $listing = null, string $field = 'file'): ListingImage
    {
        $fail = fn (string $message) => throw ValidationException::withMessages([$field => $message]);

        if (! $file->isValid()) {
            $fail($file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE
                ? 'Slika je prevelika. Najveća dozvoljena veličina je '.$this->maxMegabytes().' MB.'
                : 'Slika nije otpremljena. Pokušajte ponovo.');
        }

        if ($listing !== null && $listing->images()->count() >= $this->maxImages()) {
            $fail('Oglas može imati najviše '.$this->maxImages().' slika.');
        }

        if ($listing === null && ListingImage::query()->where('user_id', $user->id)->whereNull('listing_id')->count() >= 2 * $this->maxImages()) {
            $fail('Imate previše otpremljenih slika koje nisu u oglasu. Sačuvajte oglas pa dodajte nove.');
        }

        $stored = $this->store((string) $file->getRealPath(), (int) $file->getSize(), $listing ? (string) $listing->id : 'tmp', $fail);

        $image = DB::transaction(function () use ($stored, $user, $listing) {
            $sort = $listing ? ((int) $listing->images()->max('sort') + 1) : 0;
            if ($listing && ! $listing->images()->exists()) {
                $sort = 0;
            }

            return ListingImage::create([
                'listing_id' => $listing?->id,
                'user_id' => $user->id,
                'path' => $stored['path'],
                'hash' => $stored['hash'],
                'phash' => $stored['phash'],
                'width' => $stored['width'],
                'height' => $stored['height'],
                'size' => $stored['size'],
                'sort' => $sort,
            ]);
        });

        GenerateImageVariants::dispatch($image->id)->afterCommit();

        return $image;
    }

    /**
     * Checks the file, decodes it, applies the EXIF orientation, shrinks it and writes it
     * again (which drops EXIF and anything else hidden in the file).
     *
     * @param  callable(string): never  $fail
     * @return array{path: string, hash: string, phash: string, width: int, height: int, size: int}
     */
    private function store(string $source, int $bytes, string $folder, callable $fail): array
    {
        if ($bytes <= 0 || ! is_file($source)) {
            $fail('Slika nije otpremljena. Pokušajte ponovo.');
        }
        if ($bytes > $this->maxBytes()) {
            $fail('Slika je prevelika. Najveća dozvoljena veličina je '.$this->maxMegabytes().' MB.');
        }

        $head = (string) file_get_contents($source, false, null, 0, 64);
        if ($this->isHeic($head)) {
            $fail('HEIC slike (format sa iPhone-a) za sada ne možemo da obradimo. Sačuvajte sliku kao JPG ili PNG, ili u podešavanjima kamere izaberite „Najkompatibilnije”, pa pokušajte ponovo.');
        }

        $info = @getimagesize($source);
        $type = $info[2] ?? null;
        if ($info === false || ! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $fail('Fajl nije slika u formatu JPG, PNG ili WebP.');
        }
        [$width, $height] = $info;
        if ($width < self::MIN_EDGE || $height < self::MIN_EDGE) {
            $fail('Slika je premala. Najmanja veličina je '.self::MIN_EDGE.' × '.self::MIN_EDGE.' piksela.');
        }
        if ($width * $height > self::MAX_PIXELS) {
            $fail('Slika ima previše piksela. Smanjite je pa pokušajte ponovo.');
        }

        $this->raiseMemory();
        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
        };
        if (! $image instanceof GdImage) {
            $fail('Slika je oštećena ili nije ispravna. Pokušajte sa drugom slikom.');
        }

        if ($type === IMAGETYPE_JPEG) {
            $image = $this->orient($image, $source);
        } else {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }
        $image = $this->fit($image, self::MAX_EDGE);

        $extension = match ($type) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => 'jpg',
        };
        $data = $this->encode($image, $extension);
        $phash = $this->differenceHash($image);
        $width = imagesx($image);
        $height = imagesy($image);
        imagedestroy($image);

        $path = 'oglasi/'.$folder.'/'.Str::lower(Str::random(20)).'.'.$extension;
        $this->disk()->put($path, $data);

        return [
            'path' => $path,
            'hash' => sha1($data),
            'phash' => $phash,
            'width' => $width,
            'height' => $height,
            'size' => strlen($data),
        ];
    }

    /** HEIC/HEIF/AVIF container: "ftyp" box with a HEIF brand. */
    public function isHeic(string $head): bool
    {
        if (substr($head, 4, 4) !== 'ftyp') {
            return false;
        }

        return in_array(substr($head, 8, 4), ['heic', 'heix', 'hevc', 'hevx', 'heim', 'heis', 'mif1', 'msf1'], true);
    }

    // ---------------------------------------------------------------- listing

    /**
     * Attaches images uploaded by the user (POST /media) to the listing, in the given order.
     * Images already on the listing that are not in the list are deleted.
     *
     * @param  list<int>  $ids
     *
     * @throws ValidationException under the key $field
     */
    public function sync(Listing $listing, User $user, array $ids, string $field = 'image_ids'): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids) > $this->maxImages()) {
            throw ValidationException::withMessages([$field => 'Oglas može imati najviše '.$this->maxImages().' slika.']);
        }

        $images = ListingImage::query()->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $image = $images->get($id);
            $ownTmp = $image && $image->listing_id === null && $image->user_id === $user->id;
            if (! $image || (! $ownTmp && $image->listing_id !== $listing->id)) {
                throw ValidationException::withMessages([$field => 'Slika #'.$id.' ne postoji ili nije vaša.']);
            }
        }

        $removed = $listing->images()->whereNotIn('id', $ids)->get();
        foreach ($removed as $image) {
            $this->delete($image);
        }

        foreach ($ids as $position => $id) {
            $image = $images->get($id);
            if ($image->listing_id === null) {
                $this->moveTo($image, $listing);
            }
            $image->forceFill(['listing_id' => $listing->id, 'sort' => $position])->save();
        }

        $listing->unsetRelation('images');
    }

    /**
     * New order: the listed images first, in that order (the first one is the cover).
     *
     * @param  list<int>  $ids
     */
    public function reorder(Listing $listing, array $ids): void
    {
        $current = $listing->images()->pluck('id')->all();
        $ids = array_values(array_intersect(array_map('intval', $ids), $current));
        $order = array_merge($ids, array_values(array_diff($current, $ids)));

        DB::transaction(function () use ($order, $listing) {
            foreach ($order as $position => $id) {
                ListingImage::query()->whereKey($id)->where('listing_id', $listing->id)->update(['sort' => $position]);
            }
        });
        // query updates fire no model events; the cover may have changed
        HomeSections::flush();
        $listing->unsetRelation('images');
        $listing->unsetRelation('coverImage');
    }

    public function setCover(Listing $listing, ListingImage $image): void
    {
        $ids = $listing->images()->pluck('id')->all();
        $this->reorder($listing, array_merge([$image->id], array_diff($ids, [$image->id])));
    }

    /** Moves an image one place up (-1) or down (+1). */
    public function move(Listing $listing, ListingImage $image, int $direction): void
    {
        $ids = $listing->images()->pluck('id')->all();
        $index = array_search($image->id, $ids, true);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);
        if ($index === false || $target < 0 || $target >= count($ids)) {
            return;
        }
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        $this->reorder($listing, $ids);
    }

    /** Deletes the row and all files (original and variants). */
    public function delete(ListingImage $image): void
    {
        $this->disk()->delete($this->files($image));
        $listingId = $image->listing_id;
        $image->delete();

        if ($listingId !== null) {
            // keep positions 0..n-1 so the first one stays the cover
            $ids = ListingImage::query()->where('listing_id', $listingId)->orderBy('sort')->orderBy('id')->pluck('id');
            foreach ($ids as $position => $id) {
                ListingImage::query()->whereKey($id)->update(['sort' => $position]);
            }
        }
    }

    /** Deletes uploads that were never attached to a listing. */
    public function pruneTemporary(int $hours = 24): int
    {
        $count = 0;
        ListingImage::query()->whereNull('listing_id')->where('created_at', '<', now()->subHours($hours))
            ->chunkById(200, function ($images) use (&$count) {
                foreach ($images as $image) {
                    $this->delete($image);
                    $count++;
                }
            });

        return $count;
    }

    // ---------------------------------------------------------------- variants

    /**
     * Makes sm/md/lg in WebP and JPEG next to the original and fills `variants`, `width`, `height`.
     * Returns false when the original is missing or cannot be read.
     */
    public function generateVariants(ListingImage $image): bool
    {
        $disk = $this->disk();
        $path = $image->path;
        if (! $disk->exists($path)) {
            return false;
        }

        $this->raiseMemory();
        $source = @imagecreatefromstring((string) $disk->get($path));
        if (! $source instanceof GdImage) {
            return false;
        }
        if (! imageistruecolor($source)) {
            // palette PNG/GIF (common in imported uploads): imagewebp() refuses palette images
            imagepalettetotruecolor($source);
        }
        imagesavealpha($source, true);
        if (str_ends_with(Str::lower($path), '.jpg') || str_ends_with(Str::lower($path), '.jpeg')) {
            // imported originals may still carry the orientation in EXIF
            $source = $this->orient($source, $disk->path($path));
        }

        $base = preg_replace('/\.[a-z0-9]+$/i', '', $path);
        $variants = [];
        foreach (self::SIZES as $name => $width) {
            $resized = $this->resizeToWidth($source, $width);
            // shape shared with the listing page: {"webp": path, "jpg": path, "width": px}
            $variant = [];
            foreach (['webp', 'jpg'] as $format) {
                $file = $base.'-'.$name.'.'.$format;
                $disk->put($file, $this->encode($resized, $format));
                $variant[$format] = $file;
            }
            $variant['width'] = imagesx($resized);
            if ($resized !== $source) {
                imagedestroy($resized);
            }
            $variants[$name] = $variant;
        }

        $values = ['variants' => $variants, 'width' => imagesx($source), 'height' => imagesy($source)];
        if ($image->hash === null) {
            $values['hash'] = sha1((string) $disk->get($path));
            $values['phash'] = $this->differenceHash($source);
        }
        imagedestroy($source);

        // the image may have been moved to its listing in the meantime: then do it again there
        $fresh = ListingImage::query()->find($image->id);
        if ($fresh === null) {
            $disk->delete($this->variantFiles($variants));

            return false;
        }
        if ($fresh->path !== $path) {
            $disk->delete($this->variantFiles($variants));

            return $this->generateVariants($fresh);
        }

        $fresh->forceFill($values)->save();
        $image->setRawAttributes($fresh->getAttributes(), true);

        return true;
    }

    // ---------------------------------------------------------------- URLs

    /** Public URL of a variant; without variants (not made yet) the original. */
    public function url(ListingImage $image, string $size = 'md', string $format = 'webp'): string
    {
        $file = $image->variants[$size][$format] ?? $image->path;

        return $this->disk()->url($file);
    }

    /** Image from the API contract: {id, thumb, medium, large, width, height}. */
    public function toApi(ListingImage $image): array
    {
        return ListingFormatter::image($image) ?? [];
    }

    // ---------------------------------------------------------------- helpers

    private function moveTo(ListingImage $image, Listing $listing): void
    {
        $disk = $this->disk();
        $folder = 'oglasi/'.$listing->id.'/';
        $move = function (string $file) use ($disk, $folder) {
            $target = $folder.basename($file);
            if ($disk->exists($file) && $file !== $target) {
                $disk->move($file, $target);
            }

            return $target;
        };

        $variants = $image->variants;
        if (is_array($variants)) {
            foreach ($variants as $name => $variant) {
                foreach (['webp', 'jpg'] as $format) {
                    if (isset($variant[$format])) {
                        $variants[$name][$format] = $move($variant[$format]);
                    }
                }
            }
        }

        $image->forceFill(['path' => $move($image->path), 'variants' => $variants])->save();
    }

    /** @return list<string> */
    private function files(ListingImage $image): array
    {
        return array_merge([$image->path], $this->variantFiles((array) $image->variants));
    }

    /** @return list<string> */
    private function variantFiles(array $variants): array
    {
        $files = [];
        foreach ($variants as $variant) {
            foreach (['webp', 'jpg', 'avif'] as $format) {
                if (is_array($variant) && isset($variant[$format])) {
                    $files[] = $variant[$format];
                }
            }
        }

        return $files;
    }

    /** Applies the EXIF orientation (1–8) so the pixels are upright. */
    private function orient(GdImage $image, string $file): GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($file) : false;
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };
        if ($rotated === false) {
            return $image;
        }
        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($rotated, IMG_FLIP_HORIZONTAL);
        }
        if ($rotated !== $image) {
            imagedestroy($image);
        }

        return $rotated;
    }

    /** Shrinks so that the longest side is at most $edge (never enlarges). */
    private function fit(GdImage $image, int $edge): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);
        if (max($w, $h) <= $edge) {
            return $image;
        }
        $ratio = $edge / max($w, $h);
        $out = $this->resample($image, max(1, (int) round($w * $ratio)), max(1, (int) round($h * $ratio)));
        imagedestroy($image);

        return $out;
    }

    private function resizeToWidth(GdImage $image, int $width): GdImage
    {
        $w = imagesx($image);
        if ($w <= $width) {
            return $image;
        }

        return $this->resample($image, $width, max(1, (int) round(imagesy($image) * $width / $w)));
    }

    private function resample(GdImage $image, int $width, int $height): GdImage
    {
        $out = imagecreatetruecolor($width, $height);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));

        return $out;
    }

    private function encode(GdImage $image, string $format): string
    {
        ob_start();
        match ($format) {
            'png' => imagepng($image, null, 6),
            'webp' => imagewebp($image, null, self::QUALITY_WEBP),
            default => imagejpeg($this->flatten($image), null, self::QUALITY_JPEG),
        };

        return (string) ob_get_clean();
    }

    /** JPEG has no transparency: put the image on a white background. */
    private function flatten(GdImage $image): GdImage
    {
        $out = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagealphablending($out, true);
        imagecopyresampled($out, $image, 0, 0, 0, 0, imagesx($image), imagesy($image), imagesx($image), imagesy($image));
        imageinterlace($out, true);

        return $out;
    }

    /** 64-bit difference hash (9×8 grayscale), 16 hex characters. Same picture resized gives the same hash. */
    public function differenceHash(GdImage $image): string
    {
        $small = imagecreatetruecolor(9, 8);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 9, 8, imagesx($image), imagesy($image));

        $bits = '';
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $bits .= $this->gray($small, $x, $y) > $this->gray($small, $x + 1, $y) ? '1' : '0';
            }
        }
        imagedestroy($small);

        $hex = '';
        foreach (str_split($bits, 4) as $nibble) {
            $hex .= dechex(bindec($nibble));
        }

        return $hex;
    }

    private function gray(GdImage $image, int $x, int $y): float
    {
        $rgb = imagecolorat($image, $x, $y);

        return 0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF);
    }

    private function raiseMemory(): void
    {
        $limit = ini_get('memory_limit');
        if ($limit !== '-1' && $this->bytes((string) $limit) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
    }

    private function bytes(string $value): int
    {
        $number = (int) $value;

        return match (Str::lower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
