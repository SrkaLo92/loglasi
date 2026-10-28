<?php

namespace App\Console\Commands;

use App\Enums\ListingStatus;
use App\Mail\ListingExpired;
use App\Mail\ListingExpiringSoon;
use App\Models\Listing;
use App\Services\Catalog;
use App\Services\Notifications\InAppNotifications;
use App\Services\Posting;
use App\Services\SearchIndex;
use App\Support\Seo\Sitemap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Expires listings past expires_at and mails owners 3 days before and on expiry. Scheduled hourly. */
class ExpireListings extends Command
{
    protected $signature = 'listings:expire {--no-mail : Only expire, send no notices}';

    protected $description = 'Expire listings past their date and queue the "expires soon" mails';

    public const NOTICE_DAYS = 3;

    public const BATCH = 1000;

    public function handle(Posting $posting, Catalog $catalog, Sitemap $sitemap, SearchIndex $search): int
    {
        // in batches on the (status, expires_at) index, so one run never locks a large range
        $expired = 0;
        $justExpired = [];
        $now = now();
        do {
            $ids = Listing::query()
                ->where('status', ListingStatus::Published)
                ->where('expires_at', '<=', $now)
                ->orderBy('expires_at')
                ->limit(self::BATCH)
                ->pluck('id');
            if ($ids->isNotEmpty()) {
                $expired += Listing::query()->whereIn('id', $ids)->where('status', ListingStatus::Published)
                    ->update(['status' => ListingStatus::Expired->value, 'updated_at' => $now]);
                array_push($justExpired, ...$ids->all());
                // mass update skips model events: keep the search table in step
                $search->syncIds($ids->all());
            }
        } while ($ids->count() === self::BATCH);

        if ($expired > 0) {
            // bulk update fires no model events
            $catalog->flushCounts();
            $sitemap->flush();
        }

        $notified = $this->option('no-mail') ? 0 : $this->notify() + $this->notifyExpired($justExpired, $now);
        $drafts = $posting->pruneDrafts();

        $this->info("Isteklo: {$expired}, obavešteno: {$notified}, obrisano starih nacrta: {$drafts}.");

        return self::SUCCESS;
    }

    /** "Expired, renew for free" for the listings this run expired; none for ones that ran out days ago (after an outage). */
    private function notifyExpired(array $ids, $now): int
    {
        $count = 0;

        foreach (array_chunk($ids, 200) as $chunk) {
            Listing::query()->whereIn('id', $chunk)->where('expires_at', '>', $now->copy()->subDays(2))->with('user')
                ->each(function (Listing $listing) use (&$count) {
                    $user = $listing->user;
                    if ($user && $user->email && ! $user->isBlocked() && $user->notificationSettings()['expiring']) {
                        Mail::to($user)->queue(new ListingExpired($listing));
                        app(InAppNotifications::class)->expired($listing);
                        $count++;
                    }
                });
        }

        return $count;
    }

    private function notify(): int
    {
        $count = 0;

        Listing::query()
            ->where('status', ListingStatus::Published)
            ->whereNull('expiry_notified_at')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addDays(self::NOTICE_DAYS))
            ->with('user')
            ->chunkById(200, function ($listings) use (&$count) {
                foreach ($listings as $listing) {
                    $user = $listing->user;
                    if ($user && $user->email && ! $user->isBlocked() && $user->notificationSettings()['expiring']) {
                        Mail::to($user)->queue(new ListingExpiringSoon($listing));
                        app(InAppNotifications::class)->expiring($listing);
                        $count++;
                    }
                    Listing::query()->whereKey($listing->id)->update(['expiry_notified_at' => now()]);
                }
            });

        return $count;
    }
}
