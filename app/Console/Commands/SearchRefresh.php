<?php

namespace App\Console\Commands;

use App\Services\SearchIndex;
use App\Support\Home\HomeSections;
use Illuminate\Console\Command;

/** Every minute: drops expired listings from the search index and updates featured flags. */
class SearchRefresh extends Command
{
    protected $signature = 'search:refresh';

    protected $description = 'Remove expired listings and ended featured periods from the search index';

    public function handle(SearchIndex $index): int
    {
        if ($index->refresh() > 0) {
            HomeSections::flush();
        }

        return self::SUCCESS;
    }
}
