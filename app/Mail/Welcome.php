<?php

namespace App\Mail;

use App\Models\User;
use App\Services\Posting;
use App\Support\Settings;
use App\Support\Stranice\Cenovnik;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent once the new account's email is confirmed: how to post the first listing, the free limit, firms. */
class Welcome extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Dobro došli na loglasi.rs');
    }

    public function content(): Content
    {
        return new Content(
            view: 'nalozi.mail.welcome',
            text: 'nalozi.mail.welcome-text',
            with: [
                'name' => $this->user->name,
                'free' => Cenovnik::freeListingsText(),
                'days' => app(Posting::class)->durationDays(),
                'contact' => (string) Settings::get('contact_email'),
                'postUrl' => url_slash('postavi-oglas'),
                'listingsUrl' => url_slash('oglasi'),
                'firmsUrl' => url_slash('za-firme'),
            ],
        );
    }
}
