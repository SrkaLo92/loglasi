<?php

namespace App\Providers;

use App\Services\Payments\FakeReceiptIssuer;
use App\Services\Payments\FiscalDriver;
use App\Services\Payments\FiscalReceiptIssuer;
use App\Services\Payments\ManualReceiptIssuer;
use App\Support\Payments\PaymentMode;
use App\Support\Scheduling\InProcess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Payments: receipt issuer, rate limits for placing orders and the daily job that cancels unpaid orders. */
class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Test mode always fakes receipts; otherwise the fiscal driver (config/fiscal.php, admin setting fiscal.driver).
        $this->app->bind(FiscalReceiptIssuer::class, fn ($app) => $app->make(PaymentMode::testMode()
            ? FakeReceiptIssuer::class
            : (config('fiscal.drivers.'.FiscalDriver::current()) ?? ManualReceiptIssuer::class)));
    }

    public function boot(): void
    {
        RateLimiter::for('payments-orders', fn (Request $request) => [
            Limit::perMinute(10)->by('orders:'.($request->user()?->id ?? $request->ip())),
            Limit::perHour(30)->by('orders-hour:'.($request->user()?->id ?? $request->ip())),
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            InProcess::command($schedule, 'orders:cancel-stale')->dailyAt('03:15')->withoutOverlapping()->onOneServer();
            // Receipts the V-PFR could not issue at payment time (does nothing with the manual driver).
            InProcess::command($schedule, 'fiskal:izdaj')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
        });
    }
}
