<?php

namespace App\Mail;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "You have a new message" to the recipient. Sent from the queued SendNewMessageMail job. */
class NewMessageMail extends Mailable
{
    public function __construct(
        public User $recipient,
        public User $sender,
        public Conversation $conversation,
        public Message $latest,
        public int $unread,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nova poruka od korisnika '.$this->sender->name.' na loglasi.rs');
    }

    public function content(): Content
    {
        return new Content(text: 'messages.mail.new-message', with: [
            'url' => url_slash('moj-nalog/poruke/'.$this->conversation->id),
            'settingsUrl' => url_slash('moj-nalog'),
            'listingTitle' => $this->conversation->listing?->title,
        ]);
    }
}
