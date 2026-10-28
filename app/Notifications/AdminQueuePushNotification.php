<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Messages\PushMessage;
use App\Services\Notifications\InAppNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Push to staff: a listing waits for approval (app setting "admin_queue"). data: type listing_pending, listing_id. */
class AdminQueuePushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Listing $listing)
    {
        $this->afterCommit();
    }

    public function via(User $notifiable): array
    {
        return $notifiable->isStaff() && ($notifiable->notificationSettings()['admin_queue'] ?? false) ? [PushChannel::class] : [];
    }

    public function toPush(User $notifiable): PushMessage
    {
        return new PushMessage(
            title: 'Novi oglas čeka odobrenje',
            body: '„'.Str::limit((string) $this->listing->title, 120).'”',
            data: ['type' => InAppNotifications::LISTING_PENDING, 'listing_id' => $this->listing->id],
        );
    }
}
