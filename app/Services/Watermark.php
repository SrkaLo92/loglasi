<?php

namespace App\Services;

use GdImage;

/**
 * The loglasi logo in the bottom right corner of listing photos (md and lg variants; thumbnails stay
 * clean). The light logo (white lettering) gets a soft dark shadow, so it reads on bright and dark
 * photos, and is drawn at a fixed share of the photo width.
 */
class Watermark
{
    public const LOGO = 'img/brand/logo-light.png';

    /** Logo width as a share of the photo width, and its limits in px. */
    private const SHARE = 0.2;

    private const MIN_WIDTH = 110;

    private const MAX_WIDTH = 300;

    /** Photos narrower than this get no logo (it would cover too much). */
    private const MIN_PHOTO = 360;

    private const OPACITY = 0.85;

    /** @var array<int, array{logo: GdImage, shadow: GdImage}> prepared logos by width */
    private array $cache = [];

    public function enabled(): bool
    {
        return is_file(public_path(self::LOGO));
    }

    /** Draws the logo on the photo (in place). */
    public function apply(GdImage $photo): void
    {
        $w = imagesx($photo);
        $h = imagesy($photo);
        if ($w < self::MIN_PHOTO || $h < 120 || ! $this->enabled()) {
            return;
        }

        $width = (int) max(self::MIN_WIDTH, min(self::MAX_WIDTH, round($w * self::SHARE)));
        $prepared = $this->prepared($width);
        if ($prepared === null) {
            return;
        }
        $logo = $prepared['logo'];
        $margin = (int) max(10, round($w * 0.025));
        $x = $w - imagesx($logo) - $margin;
        $y = $h - imagesy($logo) - $margin;

        imagealphablending($photo, true);
        imagecopy($photo, $prepared['shadow'], $x + 1, $y + 2, 0, 0, imagesx($prepared['shadow']), imagesy($prepared['shadow']));
        imagecopy($photo, $logo, $x, $y, 0, 0, imagesx($logo), imagesy($logo));
    }

    /** @return array{logo: GdImage, shadow: GdImage}|null */
    private function prepared(int $width): ?array
    {
        if (isset($this->cache[$width])) {
            return $this->cache[$width];
        }
        $source = @imagecreatefrompng(public_path(self::LOGO));
        if (! $source instanceof GdImage) {
            return null;
        }
        $source = $this->trim($source);
        $height = max(1, (int) round(imagesy($source) * $width / imagesx($source)));

        $logo = imagecreatetruecolor($width, $height);
        imagealphablending($logo, false);
        imagesavealpha($logo, true);
        imagefill($logo, 0, 0, imagecolorallocatealpha($logo, 0, 0, 0, 127));
        imagecopyresampled($logo, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        imagedestroy($source);

        $shadow = imagecreatetruecolor($width, $height);
        imagealphablending($shadow, false);
        imagesavealpha($shadow, true);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($logo, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                // logo: same colours, a bit see-through; shadow: black, softer
                $logoAlpha = (int) round(127 - (127 - $alpha) * self::OPACITY);
                imagesetpixel($logo, $x, $y, ($rgba & 0xFFFFFF) | ($logoAlpha << 24));
                imagesetpixel($shadow, $x, $y, imagecolorallocatealpha($shadow, 0, 0, 0, (int) round(127 - (127 - $alpha) * 0.45)));
            }
        }
        imagefilter($shadow, IMG_FILTER_GAUSSIAN_BLUR);
        imagefilter($shadow, IMG_FILTER_GAUSSIAN_BLUR);

        return $this->cache[$width] = ['logo' => $logo, 'shadow' => $shadow];
    }

    /** Cuts the empty transparent border around the logo. */
    private function trim(GdImage $image): GdImage
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $cropped = imagecropauto($image, IMG_CROP_TRANSPARENT);
        if ($cropped instanceof GdImage) {
            imagedestroy($image);
            imagealphablending($cropped, false);
            imagesavealpha($cropped, true);

            return $cropped;
        }

        return $image;
    }

    public function __destruct()
    {
        foreach ($this->cache as $prepared) {
            imagedestroy($prepared['logo']);
            imagedestroy($prepared['shadow']);
        }
    }
}
