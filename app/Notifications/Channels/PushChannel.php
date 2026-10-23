<?php

namespace App\Notifications\Channels;

use App\Models\Device;
use App\Notifications\Messages\PushMessage;
use App\Support\Push\FcmClient;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Notification channel "push": $notification->toPush($notifiable) returns a PushMessage,
 * sent to every device token of the user (User::routeNotificationForPush()).
 * Tokens FCM reports as unregistered are deleted.
 *
 *   public function via(User $user): array { return [PushChannel::class]; }
 */
class PushChannel
{
    public function __construct(private readonly FcmClient $fcm) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $tokens = (array) $notifiable->routeNotificationFor('push', $notification);
        if ($tokens === [] || ! method_exists($notification, 'toPush')) {
            return;
        }

        if (! $this->fcm->enabled()) {
            Log::debug('Push skipped: FCM is not configured.', ['notification' => $notification::class]);

            return;
        }

        $message = $notification->toPush($notifiable);
        if (! $message instanceof PushMessage) {
            return;
        }

        $invalid = [];
        foreach ($tokens as $token) {
            if ($this->fcm->send($token, $message) === FcmClient::INVALID_TOKEN) {
                $invalid[] = $token;
            }
        }

        if ($invalid !== []) {
            Device::query()->whereIn('token', $invalid)->delete();
        }
    }
}
