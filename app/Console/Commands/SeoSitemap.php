<?php

namespace App\Console\Commands;

use App\Support\Seo\Sitemap;
use Illuminate\Console\Command;

class SeoSitemap extends Command
{
    protected $signature = 'seo:sitemap';

    protected $description = 'Rebuild the sitemap (cache) for /sitemap.xml and all its parts';

    public function handle(Sitemap $sitemap): int
    {
        $count = $sitemap->rebuild();
        $this->info('Sitemap napravljen: '.count($sitemap->parts()).' delova, '.$count.' adresa.');

        return self::SUCCESS;
    }
}
