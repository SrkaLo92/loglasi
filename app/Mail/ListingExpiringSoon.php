<?php

namespace App\Mail;

use App\Models\Listing;
use App\Notifications\Messages\ListUnsubscribe;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/** "Your listing expires in 3 days" with a renew link. Queued. */
class ListingExpiringSoon extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Listing $listing) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Vaš oglas uskoro ističe: '.$this->listing->title);
    }

    /** One-click unsubscribe from expiry reminders (notifications module). */
    public function headers(): Headers
    {
        $user = $this->listing->user;

        return new Headers(text: $user === null ? [] : ListUnsubscribe::headers(
            NotificationPreferences::unsubscribeUrl($user, NotificationPreferences::EXPIRING),
        ));
    }

    public function content(): Content
    {
        $listing = $this->listing;

        return new Content(
            view: 'posting.mail.expiring',
            text: 'posting.mail.expiring-text',
            with: [
                'title' => $listing->title,
                'name' => $listing->user?->name,
                'expires' => $listing->expires_at?->copy()->timezone(config('app.timezone'))->format('j. n. Y. \u H:i'),
                'renewUrl' => url_slash('moj-nalog/oglasi/'.$listing->id.'/obnova'),
                'listingUrl' => url_slash('oglas/'.$listing->slug),
                'myListingsUrl' => url_slash('moj-nalog/oglasi'),
            ],
        );
    }
}
