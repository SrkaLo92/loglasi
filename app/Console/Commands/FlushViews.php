<?php

namespace App\Console\Commands;

use App\Support\Listing\Stats;
use App\Support\Listing\Text;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Every minute: adds the views of cached pages (Stats::queueView) to listings, daily stats and firms. */
class FlushViews extends Command
{
    protected $signature = 'stats:flush-views';

    protected $description = 'Add the views counted on cached pages to the database';

    public function handle(): int
    {
        $file = Stats::pendingFile();
        if (! is_file($file)) {
            return self::SUCCESS;
        }

        // Renamed first: visits that arrive meanwhile start a new file and are not lost.
        $work = $file.'.'.getmypid();
        if (! @rename($file, $work)) {
            return self::SUCCESS;
        }

        $counts = ['listing' => [], 'firm' => []];
        foreach (file($work, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            [$type, $id] = array_pad(explode(' ', trim($line), 2), 2, '');
            if (isset($counts[$type]) && ctype_digit($id)) {
                $counts[$type][(int) $id] = ($counts[$type][(int) $id] ?? 0) + 1;
            }
        }

        $date = now(Text::TIMEZONE)->toDateString();
        DB::transaction(function () use ($counts, $date) {
            foreach ($counts['listing'] as $id => $n) {
                if (DB::table('listings')->where('id', $id)->increment('views', $n) > 0) {
                    DB::table('listing_daily_stats')->upsert(
                        [['listing_id' => $id, 'date' => $date, 'views' => $n]],
                        ['listing_id', 'date'],
                        ['views' => DB::raw('views + '.(int) $n)],
                    );
                }
            }
            foreach ($counts['firm'] as $id => $n) {
                DB::table('firms')->where('id', $id)->increment('profile_views', $n);
            }
        });
        @unlink($work);

        $this->info('Views added: '.array_sum($counts['listing']).' listing, '.array_sum($counts['firm']).' firm.');

        return self::SUCCESS;
    }
}
