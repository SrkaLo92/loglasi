<?php

namespace App\Console\Commands;

use App\Services\SearchIndex;
use App\Support\Home\HomeSections;
use Illuminate\Console\Command;

/** Builds the search index (listing_search) from listings again, e.g. after an import. */
class SearchRebuild extends Command
{
    protected $signature = 'search:rebuild';

    protected $description = 'Rebuild the listing search index';

    public function handle(SearchIndex $index): int
    {
        $start = microtime(true);
        $total = $index->rebuild();
        HomeSections::flush();
        $this->info(sprintf('Indeks pretrage: %d aktivnih oglasa (%.1f s).', $total, microtime(true) - $start));

        return self::SUCCESS;
    }
}
