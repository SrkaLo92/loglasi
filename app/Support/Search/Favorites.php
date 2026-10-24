<?php

namespace App\Support\Search;

use Illuminate\Support\Facades\DB;

/**
 * Favourite listings of the logged-in user, read once per request for all cards.
 * Registered as a scoped singleton (SearchServiceProvider).
 */
class Favorites
{
    /** @var array<int, true>|null */
    private ?array $ids = null;

    public function has(int $listingId): bool
    {
        if ($this->ids === null) {
            $userId = auth()->id();
            $this->ids = $userId === null ? [] : array_fill_keys(
                DB::table('favorites')->where('user_id', $userId)->pluck('listing_id')->map(fn ($id) => (int) $id)->all(),
                true,
            );
        }

        return isset($this->ids[$listingId]);
    }

    /** Action of the heart button (favourites module). */
    public static function url(int $listingId): string
    {
        return url_slash('/moj-nalog/omiljeni/'.$listingId);
    }
}
