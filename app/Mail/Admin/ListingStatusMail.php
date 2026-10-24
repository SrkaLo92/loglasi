<?php

namespace App\Mail\Admin;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the owner that their listing was approved or rejected (plain text, queued). */
class ListingStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Listing $listing, public bool $approved, public ?string $reason = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->approved
            ? 'Vaš oglas je objavljen na loglasi.rs'
            : 'Vaš oglas nije odobren na loglasi.rs');
    }

    public function content(): Content
    {
        return new Content(text: 'admin.mail.listing-status', with: [
            'title' => $this->listing->title,
            'url' => $this->listing->url(),
            'editUrl' => url_slash('moj-nalog/oglasi'),
        ]);
    }
}
