<?php

namespace App\Mail;

use App\Models\Listing;
use App\Notifications\Messages\ListUnsubscribe;
use App\Services\Posting;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/** "Your listing has expired" with a free renew button. Queued, same switch as the 3-day reminder. */
class ListingExpired extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Listing $listing) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Vaš oglas je istekao: '.$this->listing->title);
    }

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
            view: 'posting.mail.expired',
            text: 'posting.mail.expired-text',
            with: [
                'title' => $listing->title,
                'name' => $listing->user?->name,
                'days' => app(Posting::class)->durationDays(),
                'renewUrl' => url_slash('moj-nalog/oglasi/'.$listing->id.'/obnova'),
                'myListingsUrl' => url_slash('moj-nalog/oglasi').'?status=istekli',
            ],
        );
    }
}
