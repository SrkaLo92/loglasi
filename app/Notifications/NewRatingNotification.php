<?php

namespace App\Notifications;

use App\Models\SellerRating;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Messages\BrandedMailMessage;
use App\Notifications\Messages\PushMessage;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** "Nova ocena": mail and push to the seller when a buyer rates them for the first time (messages preference). */
class NewRatingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public SellerRating $rating)
    {
        $this->afterCommit();
    }

    public function via(User $notifiable): array
    {
        if (! NotificationPreferences::allows($notifiable, NotificationPreferences::MESSAGES)) {
            return [];
        }

        return array_values(array_filter([
            $notifiable->devices()->exists() ? PushChannel::class : null,
            $notifiable->hasVerifiedEmail() ? 'mail' : null,
        ]));
    }

    public function toMail(User $notifiable): BrandedMailMessage
    {
        $this->rating->loadMissing('rater:id,name', 'listing:id,title');
        $rater = BrandedMailMessage::plain($this->rating->rater?->name ?? 'Kupac');
        $listing = $this->rating->listing;

        $mail = BrandedMailMessage::to($notifiable, NotificationPreferences::MESSAGES)
            ->subject('Nova ocena: '.$this->rating->score.' od 5')
            ->greeting('Dobili ste novu ocenu')
            ->line($rater.' vas je ocenio sa '.$this->rating->score.' od 5'.($listing ? ', posle razgovora o oglasu „'.BrandedMailMessage::plain($listing->title).'”.' : '.'));
        if ($this->rating->comment) {
            $mail->line('„'.BrandedMailMessage::plain(Str::limit($this->rating->comment, 300)).'”');
        }

        return $mail->line('Možete da odgovorite javno ili da prijavite ocenu ako krši pravila.')
            ->action('Pogledaj ocenu', $this->url($notifiable));
    }

    public function toPush(User $notifiable): PushMessage
    {
        return new PushMessage(
            title: 'Nova ocena: '.$this->rating->score.' od 5',
            body: Str::limit(trim((string) $this->rating->comment) ?: 'Kupac vas je ocenio.', 140),
            data: ['type' => 'rating', 'rating_id' => $this->rating->id, 'user_id' => $notifiable->id],
        );
    }

    private function url(User $notifiable): string
    {
        return url_slash('korisnik/'.$notifiable->slug.'/ocene').'#ocena-'.$this->rating->id;
    }
}
