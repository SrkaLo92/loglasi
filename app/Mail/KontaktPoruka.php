<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Poruka sa kontakt forme, ide na adresu iz podešavanja (contact_email). */
class KontaktPoruka extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array{ime: string, email: string, telefon: string, tema: string, poruka: string, ip: ?string}  $data
     */
    public function __construct(public array $data) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            replyTo: [new Address($this->data['email'], $this->data['ime'])],
            subject: 'loglasi.rs kontakt: '.$this->data['tema'],
        );
    }

    public function content(): Content
    {
        return new Content(text: 'stranice.mail.kontakt');
    }
}
