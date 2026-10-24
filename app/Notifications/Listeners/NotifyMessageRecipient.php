<?php

namespace App\Notifications\Listeners;

use App\Events\MessageSent;
use App\Models\User;
use App\Notifications\NewMessageNotification;

/** MessageSent (after commit) → push and throttled mail to the other participant. */
class NotifyMessageRecipient
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $conversation = $message->conversation;
        if ($conversation === null) {
            return;
        }

        $recipientId = (int) $message->sender_id === (int) $conversation->buyer_id ? $conversation->seller_id : $conversation->buyer_id;
        $recipient = User::query()->find($recipientId);

        if ($recipient !== null && $recipient->id !== (int) $message->sender_id && ! $recipient->isBlocked()) {
            $recipient->notify(new NewMessageNotification($message));
        }
    }
}
