<?php

namespace App\Services\Ratings;

use App\Exceptions\RatingException;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SellerRating;
use App\Models\User;
use App\Notifications\NewRatingNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Seller ratings. Only a buyer who really talked to the seller can rate: from a conversation about a
 * listing in which both sides wrote, with a verified e-mail and an account that is not blocked. One
 * rating per buyer and seller; it can be changed for EDIT_DAYS. The seller answers once publicly or
 * reports it to the admin, who can hide it. Averages count visible ratings only and are kept on users.
 */
class SellerRatings
{
    public const EDIT_DAYS = 30;

    public const COMMENT_MAX = 500;

    public const REPLY_MAX = 500;

    /** Why $rater cannot rate in this conversation, or null when they can. */
    public function blocker(User $rater, Conversation $conversation): ?string
    {
        $existing = $this->existing($rater, $conversation);

        return match (true) {
            ! $conversation->isBuyer($rater) => 'Ocenu daje kupac, u razgovoru o oglasu prodavca.',
            (int) $conversation->seller_id === (int) $rater->id => 'Ne možete da ocenite sebe.',
            $rater->isBlocked() => 'Vaš nalog je blokiran.',
            ! $rater->hasVerifiedEmail() => 'Potvrdite e-poštu da biste ocenili prodavca.',
            $conversation->seller === null || $conversation->seller->trashed() => 'Prodavac više nema nalog.',
            ! $this->bothWrote($conversation) => 'Ocenu možete da date kad vam prodavac odgovori na poruku.',
            $existing !== null && ! $this->editable($existing) => 'Ocenu ste već dali, a menja se samo '.self::EDIT_DAYS.' dana.',
            default => null,
        };
    }

    /**
     * The rating card in a conversation: the buyer's rating so far and why they cannot rate (null = can).
     * Null for the seller's side of the conversation.
     *
     * @return array{existing: ?SellerRating, blocker: ?string}|null
     */
    public function card(User $viewer, Conversation $conversation): ?array
    {
        if (! $conversation->isBuyer($viewer)) {
            return null;
        }

        return ['existing' => $this->existing($viewer, $conversation), 'blocker' => $this->blocker($viewer, $conversation)];
    }

    public function existing(User $rater, Conversation $conversation): ?SellerRating
    {
        return SellerRating::query()->where('rater_id', $rater->id)->where('seller_id', $conversation->seller_id)->first();
    }

    public function editable(SellerRating $rating): bool
    {
        return $rating->created_at === null || $rating->created_at->gt(now()->subDays(self::EDIT_DAYS));
    }

    /** Gives or changes the buyer's rating of the conversation's seller. */
    public function rate(User $rater, Conversation $conversation, int $score, ?string $comment): SellerRating
    {
        if (($reason = $this->blocker($rater, $conversation)) !== null) {
            throw new RatingException($reason, 'not_allowed');
        }
        if ($score < 1 || $score > 5) {
            throw new RatingException('Izaberite ocenu od 1 do 5.', 'invalid_score');
        }

        $rating = DB::transaction(function () use ($rater, $conversation, $score, $comment) {
            $rating = SellerRating::query()->where('rater_id', $rater->id)->where('seller_id', $conversation->seller_id)->lockForUpdate()->first();
            $new = $rating === null;
            $rating ??= new SellerRating(['rater_id' => $rater->id, 'seller_id' => $conversation->seller_id]);
            $rating->fill([
                'conversation_id' => $conversation->id,
                'listing_id' => $conversation->listing_id,
                'score' => $score,
                'comment' => self::clean($comment, self::COMMENT_MAX),
            ])->save();
            $this->audit($rater, $new ? 'rating.created' : 'rating.updated', $rating, ['score' => $score]);

            return $rating;
        });

        $this->refresh((int) $conversation->seller_id);
        if ($rating->wasRecentlyCreated && ($seller = $conversation->seller) !== null) {
            $seller->notify(new NewRatingNotification($rating));
        }

        return $rating;
    }

    /** The seller's public answer (can be changed, empty removes it). */
    public function reply(User $seller, SellerRating $rating, ?string $text): SellerRating
    {
        $this->assertSeller($seller, $rating);
        $text = self::clean($text, self::REPLY_MAX);
        $rating->forceFill(['reply' => $text, 'replied_at' => $text !== null ? now() : null])->save();
        $this->audit($seller, 'rating.replied', $rating, []);

        return $rating;
    }

    /** The seller asks the admin to look at a rating (insults, personal data, never talked). */
    public function report(User $seller, SellerRating $rating, string $reason): SellerRating
    {
        $this->assertSeller($seller, $rating);
        $reason = self::clean($reason, 500);
        if ($reason === null) {
            throw new RatingException('Napišite zašto prijavljujete ocenu.', 'reason_required');
        }
        $rating->forceFill(['reported_at' => now(), 'report_reason' => $reason])->save();
        $this->audit($seller, 'rating.reported', $rating, ['reason' => $reason]);

        return $rating;
    }

    /** Admin: hide or show again; a handled report is cleared. */
    public function setVisible(SellerRating $rating, bool $visible, ?User $admin): void
    {
        $rating->forceFill([
            'status' => $visible ? SellerRating::VISIBLE : SellerRating::HIDDEN,
            'hidden_by' => $visible ? null : $admin?->id,
            'reported_at' => null,
        ])->save();
        $this->audit($admin, $visible ? 'rating.shown' : 'rating.hidden', $rating, ['report_reason' => $rating->report_reason]);
        $this->refresh((int) $rating->seller_id);
    }

    /** Recomputes users.rating_avg / rating_count from visible ratings. */
    public function refresh(int $sellerId): void
    {
        $row = SellerRating::query()->visible()->where('seller_id', $sellerId)
            ->selectRaw('COUNT(*) as n, AVG(score) as avg')->first();
        DB::table('users')->where('id', $sellerId)->update([
            'rating_count' => (int) ($row->n ?? 0),
            'rating_avg' => ($row->n ?? 0) > 0 ? round((float) $row->avg, 2) : null,
        ]);
        Cache::forget(self::summaryKey($sellerId));
    }

    /**
     * Average, count and how many ratings have each score (5 first).
     *
     * @return array{average: ?float, count: int, distribution: array<int, int>}
     */
    public function summary(int $sellerId): array
    {
        return Cache::remember(self::summaryKey($sellerId), 600, function () use ($sellerId) {
            $counts = SellerRating::query()->visible()->where('seller_id', $sellerId)
                ->groupBy('score')->selectRaw('score, COUNT(*) as n')->pluck('n', 'score');
            $count = (int) $counts->sum();
            $distribution = [];
            foreach ([5, 4, 3, 2, 1] as $score) {
                $distribution[$score] = (int) ($counts[$score] ?? 0);
            }
            $sum = array_sum(array_map(fn ($score, $n) => $score * $n, array_keys($distribution), $distribution));

            return ['average' => $count > 0 ? round($sum / $count, 1) : null, 'count' => $count, 'distribution' => $distribution];
        });
    }

    /** The newest visible ratings of a seller, for the profile. */
    public function latest(int $sellerId, int $limit = 3): Collection
    {
        return SellerRating::query()->visible()->where('seller_id', $sellerId)
            ->with(['rater:id,name,deleted_at', 'listing:id,title,slug,deleted_at'])
            ->latest()->limit($limit)->get();
    }

    private function bothWrote(Conversation $conversation): bool
    {
        $senders = Message::query()->where('conversation_id', $conversation->id)
            ->whereIn('sender_id', [$conversation->buyer_id, $conversation->seller_id])
            ->distinct()->count('sender_id');

        return $senders === 2;
    }

    private function assertSeller(User $seller, SellerRating $rating): void
    {
        if ((int) $rating->seller_id !== (int) $seller->id) {
            throw new RatingException('Ovo nije ocena vašeg naloga.', 'not_owner');
        }
    }

    private static function clean(?string $text, int $max): ?string
    {
        $text = trim(preg_replace('/[^\P{C}\n]+/u', '', (string) $text) ?? '');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return $text === '' ? null : mb_substr($text, 0, $max);
    }

    private static function summaryKey(int $sellerId): string
    {
        return 'ratings.summary.'.$sellerId;
    }

    /** @param  array<string, mixed>  $changes */
    private function audit(?User $actor, string $action, SellerRating $rating, array $changes): void
    {
        AuditLog::create([
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $rating->getMorphClass(),
            'subject_id' => $rating->getKey(),
            'changes' => $changes,
            'ip' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
