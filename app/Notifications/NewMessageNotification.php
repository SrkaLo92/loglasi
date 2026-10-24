<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Messages\BrandedMailMessage;
use App\Notifications\Messages\PushMessage;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Nova poruka": push for every message; mail at most once per conversation per
 * notifications.message_mail_throttle_minutes, and not when the recipient has already
 * read the conversation by the time the queued mail runs. Sent from MessageSent
 * (App\Notifications\Listeners\NotifyMessageRecipient); the messages module sends nothing.
 */
class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Message $message)
    {
        $this->afterCommit();
    }

    public function via(User $notifiable): array
    {
        if (! NotificationPreferences::allows($notifiable, NotificationPreferences::MESSAGES)) {
            return [];
        }

        $channels = [];

        if ($notifiable->devices()->exists()) {
            $channels[] = PushChannel::class;
        }

        $minutes = (int) config('notifications.message_mail_throttle_minutes', 15);
        $key = 'notifications.message_mail.'.$notifiable->id.'.'.$this->message->conversation_id;
        if ($notifiable->hasVerifiedEmail() && Cache::add($key, true, now()->addMinutes($minutes))) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** The mail waits in the queue; if the recipient opened the conversation meanwhile, it is dropped. */
    public function shouldSend(User $notifiable, string $channel): bool
    {
        if ($channel !== 'mail') {
            return true;
        }

        // Unread counter of the recipient (messages module): 0 = opened after this message.
        $conversation = $this->message->conversation()->first();

        return $conversation !== null && $conversation->unreadFor($notifiable) > 0;
    }

    public function toMail(User $notifiable): BrandedMailMessage
    {
        $this->message->loadMissing('sender:id,name', 'conversation.listing:id,title,slug');
        $sender = $this->message->sender?->name ?? 'Korisnik';
        $listing = $this->message->conversation?->listing;

        $mail = BrandedMailMessage::to($notifiable, NotificationPreferences::MESSAGES)
            ->subject('Nova poruka od korisnika '.$sender)
            ->greeting('Imate novu poruku')
            ->line($listing ? BrandedMailMessage::plain($sender).' vam je pisao o oglasu „'.BrandedMailMessage::plain($listing->title).'”:' : BrandedMailMessage::plain($sender).' vam je poslao poruku:')
            ->line('„'.BrandedMailMessage::plain(Str::limit($this->message->body, 300)).'”')
            ->action('Odgovori', $this->conversationUrl());

        return $mail->line('Ne odgovarajte na ovaj mejl, poruku pošaljite preko sajta ili aplikacije.');
    }

    public function toPush(User $notifiable): PushMessage
    {
        $this->message->loadMissing('sender:id,name');

        return new PushMessage(
            title: $this->message->sender?->name ?? 'Nova poruka',
            body: Str::limit(trim($this->message->body), 140),
            data: [
                'type' => 'message',
                'conversation_id' => $this->message->conversation_id,
                'message_id' => $this->message->id,
            ],
        );
    }

    private function conversationUrl(): string
    {
        return url_slash(str_replace('{id}', (string) $this->message->conversation_id, (string) config('notifications.links.conversation')));
    }
}
