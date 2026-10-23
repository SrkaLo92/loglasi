<?php

namespace App\Console\Commands;

use App\Services\ListingImages;
use Illuminate\Console\Command;

/** Removes uploaded images that were never attached to a listing. */
class PruneImages extends Command
{
    protected $signature = 'images:prune {--hours=24 : Age in hours}';

    protected $description = 'Delete unattached uploaded listing images';

    public function handle(ListingImages $images): int
    {
        $count = $images->pruneTemporary(max(1, (int) $this->option('hours')));
        $this->info("Obrisano slika: {$count}.");

        return self::SUCCESS;
    }
}
