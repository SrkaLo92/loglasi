<?php

namespace App\Console\Commands;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\User;
use App\Services\Payments\PlusSeller;
use App\Services\Posting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Plus prodavac: every 15 days the seller's published listings are renewed by themselves (moved up like a
 * fresh listing and the expiry pushed out), so they never expire while the term runs. Scheduled daily.
 */
class RenewPlusSellerListings extends Command
{
    protected $signature = 'listings:plus-renew';

    protected $description = 'Renew the listings of Plus prodavac sellers every 15 days';

    public function handle(Posting $posting): int
    {
        $due = now()->subDays(PlusSeller::RENEW_DAYS);
        $renewed = 0;

        $userIds = DB::table('plus_seller_terms')->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->distinct()->pluck('user_id');

        foreach (User::query()->whereIn('id', $userIds)->get() as $user) {
            if ($user->isBlocked() || $user->isFirm()) {
                continue;
            }
            Listing::query()
                ->where('user_id', $user->id)
                ->where('status', ListingStatus::Published)
                ->whereRaw('COALESCE(bumped_at, published_at) <= ?', [$due])
                ->with('category.parent.parent')
                ->chunkById(100, function ($listings) use ($posting, &$renewed) {
                    foreach ($listings as $listing) {
                        if (! PlusSeller::appliesTo($listing->category)) {
                            continue;
                        }
                        $until = now()->addDays($posting->durationDays());
                        $listing->bumped_at = now();
                        if ($listing->expires_at === null || $listing->expires_at->lessThan($until)) {
                            $listing->expires_at = $until;
                            $listing->expiry_notified_at = null;
                        }
                        $listing->save();
                        $renewed++;
                    }
                });
        }

        $this->info("Obnovljeno oglasa (Plus prodavac): {$renewed}.");

        return self::SUCCESS;
    }
}
