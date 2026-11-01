<?php

namespace App\Console\Commands\Info;

use App\Support\Info\PowerOutages;
use App\Support\PageCache;
use Illuminate\Console\Command;

class SyncPowerOutages extends Command
{
    protected $signature = 'info:power-outages';

    protected $description = 'Info portal: read planned power outages for Loznica and the places around it';

    public function handle(): int
    {
        if (! config('info.enabled')) {
            return self::SUCCESS;
        }
        $result = PowerOutages::sync();
        if ($result['saved'] > 0) {
            PageCache::flush();
        }
        $this->line(sprintf('Pages read: %d, failed: %d, new outages: %d.', $result['pages'], $result['failed'], $result['saved']));

        return self::SUCCESS;
    }
}
