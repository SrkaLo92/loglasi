<?php

namespace App\Console\Commands;

use App\Enums\ListingStatus;
use App\Jobs\Notifications\MatchSavedSearches;
use App\Jobs\Notifications\SendSavedSearchDigests;
use App\Models\Listing;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;

/**
 * Hourly: takes the listings published since the previous run (indexed window on
 * status + published_at), queues them in chunks for matching, then the digest job.
 */
class SendSavedSearchAlerts extends Command
{
    public const WATERMARK = 'notifications.saved_search_watermark';

    protected $signature = 'notifications:saved-searches';

    protected $description = 'Match newly published listings against saved searches and send the digests';

    public function handle(): int
    {
        $config = config('notifications.saved_searches');
        $to = now();
        $from = $this->windowStart($to, $config);

        $ids = Listing::query()
            ->where('status', ListingStatus::Published)
            ->where('published_at', '>', $from)
            ->where('published_at', '<=', $to)
            ->orderBy('id')
            ->pluck('id');

        $jobs = $ids->chunk(max(1, (int) $config['chunk']))
            ->map(fn ($chunk) => new MatchSavedSearches($chunk->values()->all()))
            ->values()->all();
        $jobs[] = new SendSavedSearchDigests;

        Bus::chain($jobs)->dispatch();
        Settings::set(self::WATERMARK, $to->toIso8601String());

        $this->info(sprintf('%d new listings since %s, %d match jobs queued.', $ids->count(), $from->toDateTimeString(), count($jobs) - 1));

        return self::SUCCESS;
    }

    private function windowStart(Carbon $to, array $config): Carbon
    {
        $earliest = $to->copy()->subHours((int) $config['max_window_hours']);
        $stored = Settings::get(self::WATERMARK);
        $from = is_string($stored) ? Carbon::parse($stored)->subMinutes((int) $config['overlap_minutes']) : $to->copy()->subHour();

        return $from->lessThan($earliest) ? $earliest : $from;
    }
}
