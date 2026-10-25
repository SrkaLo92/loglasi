<?php

namespace App\Console\Commands;

use App\Services\Notifications\InAppNotifications;
use Illuminate\Console\Command;

/** Removes in-app notifications older than InAppNotifications::KEEP_DAYS. Sends nothing. */
class PruneInAppNotifications extends Command
{
    protected $signature = 'notifications:prune-in-app';

    protected $description = 'Delete old in-app notifications';

    public function handle(InAppNotifications $inApp): int
    {
        $this->info('Obrisano obaveštenja: '.$inApp->prune().'.');

        return self::SUCCESS;
    }
}
