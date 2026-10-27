<?php

namespace App\Mail;

use App\Models\User;
use App\Services\Posting;
use App\Support\Accounts\PublicName;
use App\Support\Notifications\Announcements;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Pages\Pricing;
use App\Support\Settings;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** One-off news mail to a user (mail:announce); unsubscribes from "Novosti sa loglasi.rs". */
class Announcement extends BrandedMailable
{
    protected ?string $category = NotificationPreferences::NEWSLETTER;

    public function __construct(User $user, public string $campaign)
    {
        parent::__construct($user);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: Announcements::get($this->campaign)['subject'],
            replyTo: [new Address((string) Settings::get('contact_email'), 'loglasi.rs')],
        );
    }

    public function unsubscribeUrl(): ?string
    {
        // A test copy (mail:announce --test) goes to an address without an account.
        return $this->user->exists ? parent::unsubscribeUrl() : url_slash('moj-nalog/obavestenja');
    }

    public function content(): Content
    {
        $campaign = Announcements::get($this->campaign);
        $view = 'notifications.mail.announcements.'.$campaign['view'];

        return new Content(view: $view, text: $view.'-text', with: [
            // The placeholder name of cleaned accounts reads badly in a greeting.
            'name' => ($name = trim((string) $this->user->name)) === PublicName::FALLBACK ? '' : $name,
            'preheader' => $campaign['preheader'],
            'free' => Pricing::freeListingsText(),
            'days' => app(Posting::class)->durationDays(),
            'postUrl' => url_slash('postavi-oglas'),
            'resetUrl' => url_slash('zaboravljena-lozinka'),
            'homeUrl' => url_slash(''),
            'unsubscribeUrl' => $this->unsubscribeUrl(),
        ]);
    }
}
