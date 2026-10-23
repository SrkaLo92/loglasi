<?php

namespace App\Mail\Firms;

use App\Models\Firm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Reminder that the Biznis package ends soon (14 and 3 days before biznis_until). Queued, plain text. */
class BiznisExpiringSoon extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Firm $firm, public int $daysLeft) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->daysLeft <= 3
            ? 'Biznis paket ističe za '.static::days($this->daysLeft)
            : 'Biznis paket ističe '.$this->firm->biznis_until->timezone(config('app.timezone'))->format('j. n. Y.'));
    }

    public function content(): Content
    {
        return new Content(text: 'firms.mail.biznis-expiring', with: [
            'name' => $this->firm->name,
            'until' => $this->firm->biznis_until->timezone(config('app.timezone'))->format('j. n. Y.'),
            'days' => static::days($this->daysLeft),
            'renewUrl' => url_slash(trim((string) config('firms.renew_url', '/cenovnik/'), '/')),
            'accountUrl' => url_slash('moj-nalog/firma'),
        ]);
    }

    /** "1 dan", "3 dana", "14 dana", "21 dan". */
    public static function days(int $days): string
    {
        return $days.' '.($days % 10 === 1 && $days % 100 !== 11 ? 'dan' : 'dana');
    }
}
