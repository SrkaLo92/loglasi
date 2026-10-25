<?php

namespace App\Console\Commands;

use App\Jobs\GenerateImageVariants;
use App\Models\ListingImage;
use App\Services\ListingImages;
use Illuminate\Console\Command;

/** Makes missing image variants (imported WordPress images, failed jobs) in batches. */
class ImageVariants extends Command
{
    protected $signature = 'images:variants
        {--batch=200 : Images per run}
        {--queue : Dispatch jobs instead of working here}
        {--all : Make variants again for all images (e.g. to add the watermark), in batches until done}';

    protected $description = 'Generate missing listing image variants (sm, md, lg in WebP and JPEG)';

    public function handle(ListingImages $images): int
    {
        $batch = max(1, (int) $this->option('batch'));
        $query = ListingImage::query()->orderBy('id');
        if (! $this->option('all')) {
            $query->whereNull('variants');
        }

        $done = 0;
        $failed = 0;
        $todo = $this->option('all') ? $query->lazyById($batch) : $query->limit($batch)->get();
        foreach ($todo as $image) {
            if ($this->option('queue')) {
                GenerateImageVariants::dispatch($image->id);
                $done++;

                continue;
            }
            if ($images->generateVariants($image)) {
                $done++;
            } else {
                $failed++;
                // mark as tried, so a missing file does not block the next batches
                $image->forceFill(['variants' => []])->save();
                $this->warn("Slika #{$image->id}: nema fajla ili nije ispravna ({$image->path}).");
            }
        }

        $left = ListingImage::query()->whereNull('variants')->count();
        $this->info(($this->option('queue') ? 'Poslato u red' : 'Napravljeno').": {$done}, neuspešno: {$failed}, bez varijanti ostalo: {$left}.");

        return self::SUCCESS;
    }
}
