<?php

namespace App\Support\Ratings;

use App\Models\SellerRating;
use App\Models\User;
use App\Support\Listing\Text;

/** How a rating is shown on the site and in the API: the rater as "Marko P.", the average as "4,8". */
final class RatingPresenter
{
    /** First name and the initial of the last name, so the rater stays recognisable but not exposed. */
    public static function raterName(?User $rater): string
    {
        if ($rater === null || $rater->trashed()) {
            return 'Bivši korisnik';
        }
        $parts = preg_split('/\s+/u', trim(Text::displayName((string) $rater->name))) ?: [];
        $first = $parts[0] ?? 'Kupac';
        $last = count($parts) > 1 ? mb_strtoupper(mb_substr((string) end($parts), 0, 1)).'.' : '';

        return trim($first.' '.$last);
    }

    public static function average(?float $average): string
    {
        return $average === null ? '' : number_format($average, 1, ',', '');
    }

    /** "1 ocena", "3 ocene", "5 ocena". */
    public static function count(int $count): string
    {
        return $count.' '.Text::plural($count, 'ocena', 'ocene', 'ocena');
    }

    /** @return array<string, mixed> one rating for the API */
    public static function api(SellerRating $rating, ?User $viewer = null): array
    {
        $own = $viewer !== null && (int) $viewer->id === (int) $rating->seller_id;

        return [
            'id' => (int) $rating->id,
            'score' => (int) $rating->score,
            'comment' => $rating->comment,
            'rater_name' => self::raterName($rating->rater),
            'listing' => $rating->listing ? ['id' => (int) $rating->listing->id, 'title' => (string) $rating->listing->title] : null,
            'reply' => $rating->reply,
            'replied_at' => $rating->replied_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'created_at' => $rating->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'can_reply' => $own,
            'can_report' => $own && $rating->reported_at === null,
        ];
    }

    /** @return array{average: ?float, count: int} for seller cards */
    public static function brief(User $user): array
    {
        return ['average' => $user->rating_count > 0 ? round((float) $user->rating_avg, 1) : null, 'count' => (int) $user->rating_count];
    }
}
