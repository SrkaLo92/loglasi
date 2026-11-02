<?php

namespace App\Providers;

use App\Models\InfoAd;
use App\Models\InfoBusiness;
use App\Models\InfoPharmacyDuty;
use App\Models\InfoPost;
use App\Models\InfoServiceNotice;
use App\Support\Info\InfoAds;
use App\Support\Info\InfoToday;
use App\Support\PageCache;
use App\Support\Scheduling\InProcess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Info portal module: schedule (weather, power outages), cache clearing, rate limits. */
class InfoServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Published content changes what guests see on cached pages.
        $changed = function () {
            InfoToday::forget();
            InfoAds::forget();
            PageCache::flush();
        };
        foreach ([InfoPost::class, InfoServiceNotice::class, InfoPharmacyDuty::class, InfoBusiness::class, InfoAd::class] as $model) {
            $model::saved($changed);
            $model::deleted($changed);
        }

        RateLimiter::for('info-submit', fn (Request $request) => [
            Limit::perHour(5)->by('info-submit:'.$request->ip()),
            Limit::perDay(300)->by('info-submit'),
        ]);
        RateLimiter::for('info-drafts', fn (Request $request) => Limit::perMinute(30)->by('info-drafts'));

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            InProcess::command($schedule, 'info:weather')->hourlyAt(7)->withoutOverlapping(10);
            InProcess::command($schedule, 'info:power-outages')->cron('20 6,12,18 * * *')->withoutOverlapping(10)->onOneServer();
            InProcess::command($schedule, 'info:fetch-news')->hourlyAt(25)->withoutOverlapping(30)->onOneServer();
        });
    }
}
