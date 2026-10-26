<?php

namespace App\WordPressImport\Steps;

use App\WordPressImport\Text;
use Illuminate\Support\Facades\DB;

/** Favourite listings from the user meta (Listivo stores a list of listing IDs). */
class FavoriteImport extends Step
{
    public function run(): void
    {
        $key = (string) config('wordpress_import.usermeta.favorites');
        if ($key === '') {
            return;
        }

        $users = $this->ctx->map('users');
        $listings = $this->ctx->map('listings');

        $this->wp->table('usermeta')->where('meta_key', $key)->orderBy('umeta_id')
            ->chunk(500, function ($rows) use ($users, $listings) {
                foreach ($rows as $row) {
                    $wpUser = (int) $row->user_id;
                    foreach (Text::idList($row->meta_value) as $wpListing) {
                        $this->report->wp('favorites');
                        $userId = $users[$wpUser] ?? null;
                        $listingId = $listings[$wpListing] ?? null;
                        if ($userId === null || $listingId === null) {
                            $this->report->skipped('favorites', "korisnik #{$wpUser}, oglas #{$wpListing}: ".($userId === null ? 'korisnik' : 'oglas').' nije uvezen');

                            continue;
                        }
                        DB::table('favorites')->insertOrIgnore(['user_id' => $userId, 'listing_id' => $listingId, 'created_at' => now()]);
                        $this->report->imported('favorites');
                    }
                }
            });
    }
}
