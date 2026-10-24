<?php

namespace App\Support\Search;

use App\Models\Listing;
use Illuminate\Support\Str;

/**
 * Premium XL card: the wide card (two columns on desktop) of listings with a running Premium XL.
 * At most one per config('payments.premium_xl_every') listings of a list (price list: 1 per 8–12);
 * the XL listings beyond that keep their rank but use the normal card.
 */
class PremiumXl
{
    /** Characters of the description shown on the card. */
    public const EXCERPT = 180;

    /**
     * Ids of the listings in this list drawn as the XL card, in list order.
     *
     * @param  iterable<Listing>  $listings
     * @return array<int, true>
     */
    public static function cards(iterable $listings): array
    {
        // A paginator is iterable too, but collect() would take its array form.
        $listings = collect(is_array($listings) ? $listings : iterator_to_array($listings, false));
        $every = max(1, (int) config('payments.premium_xl_every', 10));
        $allowed = (int) ceil($listings->count() / $every);

        return $listings->filter(fn (Listing $l) => $l->isPremiumXl())
            ->take($allowed)
            ->mapWithKeys(fn (Listing $l) => [(int) $l->id => true])
            ->all();
    }

    /** Plain one-paragraph start of the description. */
    public static function excerpt(?string $description): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $description)));

        return Str::limit($text, self::EXCERPT, '…', preserveWords: true);
    }
}
