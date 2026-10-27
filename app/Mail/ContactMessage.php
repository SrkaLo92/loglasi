<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

/** Message from the contact form (HTML + plain text), sent to the address from the settings (contact_email). */
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
            subject: 'Kontakt forma: '.$this->data['tema'].' ('.$this->data['ime'].')',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'pages.mail.contact-html', text: 'pages.mail.contact');
    }

    /** The sender's IP stays out of the body (spam filters dislike it there) but is kept for abuse checks. */
    public function headers(): Headers
    {
        return new Headers(text: ($this->data['ip'] ?? null) ? ['X-Sender-IP' => (string) $this->data['ip']] : []);
    }
}
