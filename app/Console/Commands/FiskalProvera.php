<?php

namespace App\Console\Commands;

use App\Enums\BuyerType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Payments\FiscalDriver;
use App\Services\Payments\Vpfr\VpfrClient;
use App\Services\Payments\Vpfr\VpfrReceiptIssuer;
use App\Support\Payments\PaymentMode;
use Illuminate\Console\Command;
use Throwable;

/** Shows how receipts are issued and, with the V-PFR driver, whether the processor answers. Changes nothing. */
class FiskalProvera extends Command
{
    protected $signature = 'fiskal:provera';

    protected $description = 'Proverava podešavanje fiskalne kase (V-PFR) i narudžbine bez računa';

    public function handle(VpfrClient $client): int
    {
        $this->line('Način izdavanja: '.FiscalDriver::OPTIONS[FiscalDriver::current()]);
        $this->line('Test režim plaćanja: '.(PaymentMode::testMode() ? 'uključen (računi su probni)' : 'isključen'));
        $this->line('Poreska oznaka: '.VpfrReceiptIssuer::taxLabel());

        $missing = Order::query()->whereIn('status', [OrderStatus::Paid, OrderStatus::Activated])
            ->where('is_test', false)->where('buyer_type', BuyerType::Private)->whereNull('receipt_number')->count();
        $this->line("Plaćene narudžbine privatnih lica bez računa: {$missing}");

        if (! $client->configured()) {
            $this->warn('V-PFR nije podešen (FISCAL_VPFR_URL i FISCAL_VPFR_PAC ili FISCAL_VPFR_TOKEN u .env).');

            return FiscalDriver::automatic() ? self::FAILURE : self::SUCCESS;
        }

        try {
            $status = $client->status();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('V-PFR odgovara.');
        foreach (['sdcDateTime' => 'Vreme', 'uid' => 'UID', 'deviceSerialNumber' => 'Serijski broj', 'softwareVersion' => 'Verzija'] as $key => $label) {
            if (isset($status[$key]) && is_scalar($status[$key])) {
                $this->line("{$label}: {$status[$key]}");
            }
        }
        foreach ((array) ($status['currentTaxRates']['taxCategories'] ?? []) as $category) {
            foreach ((array) ($category['taxRates'] ?? []) as $rate) {
                if (isset($rate['label'])) {
                    $this->line('Poreska oznaka '.$rate['label'].': '.($category['name'] ?? '').' '.($rate['rate'] ?? '').'%');
                }
            }
        }
        if (isset($status['gsc']) && is_array($status['gsc']) && $status['gsc'] !== []) {
            $this->warn('Upozorenja: '.implode(', ', array_map('strval', $status['gsc'])));
        }

        return self::SUCCESS;
    }
}
