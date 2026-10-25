<?php

namespace App\Console\Commands;

use App\Enums\BuyerType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderFulfillment;
use App\Services\Payments\FiscalDriver;
use App\Services\Payments\PaymentConfirmation;
use App\Support\Payments\PaymentMode;
use Illuminate\Console\Command;

/**
 * Issues the fiscal receipts the V-PFR could not issue when the payment was confirmed (it was down).
 * Every 15 minutes; only with the V-PFR driver and outside test mode. Retrying is safe: the request
 * id is the same, so the processor never makes a second receipt for an order.
 */
class FiskalIzdaj extends Command
{
    protected $signature = 'fiskal:izdaj {--days=30 : only orders paid in the last N days}';

    protected $description = 'Izdaje fiskalne račune za plaćene narudžbine koje ih još nemaju (V-PFR)';

    public function handle(OrderFulfillment $fulfillment): int
    {
        if (! FiscalDriver::automatic() || PaymentMode::testMode()) {
            return self::SUCCESS;
        }

        $issued = 0;
        $failed = 0;
        Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Activated])
            ->where('is_test', false)->where('buyer_type', BuyerType::Private)->whereNull('receipt_number')
            ->where('paid_at', '>=', now()->subDays(max(1, (int) $this->option('days'))))
            ->orderBy('id')->limit(100)->get()
            ->each(function (Order $order) use ($fulfillment, &$issued, &$failed) {
                $fulfillment->recordReceipt($order, new PaymentConfirmation) ? $issued++ : $failed++;
            });

        if ($issued + $failed > 0) {
            $this->line("Izdato: {$issued}, nije uspelo: {$failed}.");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
