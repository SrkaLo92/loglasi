<?php

namespace App\Jobs;

use App\Models\ListingImage;
use App\Services\ListingImages;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Makes the sm/md/lg variants (WebP + JPEG) of one listing image. */
class GenerateImageVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public int $imageId) {}

    public function handle(ListingImages $images): void
    {
        $image = ListingImage::query()->find($this->imageId);
        if ($image !== null) {
            $images->generateVariants($image);
        }
    }
}
