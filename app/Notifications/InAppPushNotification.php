<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\UserNotification;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Messages\PushMessage;
use App\Services\Notifications\InAppNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Push copy of a bell notification for the apps (every bell type except rating and saved_search,
 * which have their own push in NewRatingNotification and SavedSearchDigestNotification).
 * data: type (same as the bell item), notification_id, listing_id.
 */
class InAppPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    /** Bell type → the app setting (User::NOTIFICATION_DEFAULTS) that turns its push off. */
    public const SETTING = [
        InAppNotifications::LISTING_APPROVED => 'listing_status',
        InAppNotifications::LISTING_REJECTED => 'listing_status',
        InAppNotifications::FAVORITE => 'messages',
        InAppNotifications::LISTING_EXPIRING => 'expiring',
        InAppNotifications::LISTING_EXPIRED => 'expiring',
        InAppNotifications::ORDER_PAID => 'payments',
        InAppNotifications::PACKAGE_EXPIRING => 'payments',
    ];

    public function __construct(public UserNotification $item)
    {
        $this->afterCommit();
    }

    public function via(User $notifiable): array
    {
        $setting = self::SETTING[$this->item->type] ?? null;
        if ($setting === null || ! ($notifiable->notificationSettings()[$setting] ?? true)) {
            return [];
        }

        return $notifiable->devices()->exists() ? [PushChannel::class] : [];
    }

    public function toPush(User $notifiable): PushMessage
    {
        $p = app(InAppNotifications::class)->present($this->item);

        return new PushMessage(
            title: $p['title'],
            body: Str::limit((string) $p['body'], 140),
            data: array_filter([
                'type' => $this->item->type,
                'notification_id' => $this->item->id,
                'listing_id' => $this->item->listing_id,
            ], fn ($v) => $v !== null),
        );
    }
}
