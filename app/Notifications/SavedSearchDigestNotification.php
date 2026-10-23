<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Messages\BrandedMailMessage;
use App\Notifications\Messages\PushMessage;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * One digest per user with the new listings of all their saved searches.
 * Built by App\Jobs\Notifications\SendSavedSearchDigests; holds plain arrays, not models,
 * so the queued payload stays small and does not depend on later changes.
 */
class SavedSearchDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<array{id: int, name: string, url: string, total: int, listings: list<array{title: string, price: string, location: ?string, url: string}>}>  $searches
     */
    public function __construct(public array $searches) {}

    public function via(User $notifiable): array
    {
        if (! NotificationPreferences::allows($notifiable, NotificationPreferences::SAVED_SEARCHES)) {
            return [];
        }

        $channels = [];
        if ($notifiable->hasVerifiedEmail()) {
            $channels[] = 'mail';
        }
        if ($notifiable->devices()->exists()) {
            $channels[] = PushChannel::class;
        }

        return $channels;
    }

    public function total(): int
    {
        return array_sum(array_column($this->searches, 'total'));
    }

    public function toMail(User $notifiable): BrandedMailMessage
    {
        $total = $this->total();

        return BrandedMailMessage::to($notifiable, NotificationPreferences::SAVED_SEARCHES)
            ->subject(count($this->searches) === 1
                ? self::newListings($total).' za pretragu „'.$this->searches[0]['name'].'”'
                : self::newListings($total).' za vaše sačuvane pretrage')
            ->markdown('notifications.mail.saved-search-digest', [
                'searches' => $this->searches,
                'total' => $total,
                'manageUrl' => url_slash('moj-nalog/pretrage'),
            ]);
    }

    public function toPush(User $notifiable): PushMessage
    {
        $first = $this->searches[0];
        $body = count($this->searches) === 1
            ? '„'.$first['name'].'”: '.mb_strtolower(self::newListings($first['total']))
            : self::newListings($this->total()).' za '.count($this->searches).' pretrage';

        return new PushMessage(
            title: 'Novi oglasi za vaše pretrage',
            body: $body,
            data: [
                'type' => 'saved_search',
                'saved_search_id' => $first['id'],
                'url' => $first['url'],
            ],
        );
    }

    /** "1 novi oglas", "3 nova oglasa", "5 novih oglasa", "21 novi oglas". */
    public static function newListings(int $n): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $n.' novi oglas';
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $n.' nova oglasa';
        }

        return $n.' novih oglasa';
    }
}
