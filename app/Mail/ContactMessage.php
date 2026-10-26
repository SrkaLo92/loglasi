<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Message from the contact form, sent to the address from the settings (contact_email). */
class ContactMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{ime: string, email: string, telefon: string, tema: string, poruka: string, ip: ?string}  $data
     */
    public function __construct(public array $data) {}

    public function envelope(): Envelope
    {
        // Reply-to only when the sender left an e-mail; a phone number is called back.
        return new Envelope(
            replyTo: $this->data['email'] !== '' ? [new Address($this->data['email'], $this->data['ime'])] : [],
            subject: 'loglasi.rs: '.$this->data['tema'],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'pages.mail.contact');
    }
}
