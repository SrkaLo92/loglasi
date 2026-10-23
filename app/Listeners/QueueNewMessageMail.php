<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Jobs\SendNewMessageMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Batching: at most one email per recipient per conversation in `messages.mail_window_minutes`.
 * The job runs after a short delay and is skipped when the recipient has read the conversation meanwhile.
 */
class QueueNewMessageMail
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $conversation = $message->conversation;
        $recipientId = (int) $message->sender_id === (int) $conversation->buyer_id ? $conversation->seller_id : $conversation->buyer_id;
        $recipient = User::query()->find($recipientId);

        if (! $recipient || ! $this->wantsMail($recipient)) {
            return;
        }

        $key = 'messages.mail.'.$conversation->id.'.'.$recipient->id;
        if (! Cache::add($key, true, now()->addMinutes((int) config('messages.mail_window_minutes')))) {
            return;
        }

        SendNewMessageMail::dispatch($conversation->id, $recipient->id)
            ->delay(now()->addSeconds((int) config('messages.mail_delay_seconds')))
            ->afterCommit();
    }

    private function wantsMail(User $user): bool
    {
        return ! $user->isBlocked()
            && $user->hasVerifiedEmail()
            && ($user->notificationSettings()['messages'] ?? true);
    }
}
