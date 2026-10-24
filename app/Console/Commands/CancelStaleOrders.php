<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

/** Cancels orders that have waited for payment longer than payments.pending_days (14). Scheduled daily. */
class CancelStaleOrders extends Command
{
    protected $signature = 'orders:cancel-stale';

    protected $description = 'Cancel unpaid orders older than the payment deadline';

    public function handle(OrderService $orders): int
    {
        $count = $orders->cancelStale();
        $this->info("Cancelled {$count} unpaid order(s).");

        return self::SUCCESS;
    }
}
