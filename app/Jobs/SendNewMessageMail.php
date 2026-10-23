<?php

namespace App\Jobs;

use App\Mail\NewMessageMail;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

/** Emails the recipient about unread messages in one conversation, unless they were read meanwhile. */
class SendNewMessageMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $conversationId, public int $recipientId) {}

    public function handle(): void
    {
        $conversation = Conversation::query()->with('listing:id,title,slug,deleted_at')->find($this->conversationId);
        $recipient = User::query()->find($this->recipientId);

        if (! $conversation || ! $recipient || ! $conversation->involves($recipient)) {
            return;
        }

        // Settings may have changed during the delay.
        if ($recipient->isBlocked() || ! ($recipient->notificationSettings()['messages'] ?? true)) {
            return;
        }

        $unread = $conversation->unreadFor($recipient);
        if ($unread === 0) {
            return;
        }

        $latest = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('sender_id', '!=', $recipient->id)
            ->orderByDesc('id')
            ->first();
        $sender = $latest ? User::query()->find($latest->sender_id) : null;

        if (! $latest || ! $sender) {
            return;
        }

        Mail::to($recipient)->send(new NewMessageMail($recipient, $sender, $conversation, $latest, $unread));
    }
}
