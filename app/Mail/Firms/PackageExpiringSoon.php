<?php

namespace App\Mail\Firms;

use App\Models\Firm;
use App\Support\Firms\FirmPackage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Reminder that a firm package (or the free first year) ends soon. Queued, plain text, Serbian. */
class PackageExpiringSoon extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Firm $firm, public int $daysLeft) {}

    public function envelope(): Envelope
    {
        $what = $this->firm->free_program ? 'Besplatna godina' : 'Paket '.$this->label();

        return new Envelope(subject: $this->daysLeft <= 3
            ? $what.' ističe za '.static::days($this->daysLeft)
            : $what.' ističe '.$this->until());
    }

    public function content(): Content
    {
        return new Content(text: 'firms.mail.package-expiring', with: [
            'name' => $this->firm->name,
            'label' => $this->label(),
            'free' => (bool) $this->firm->free_program,
            'until' => $this->until(),
            'days' => static::days($this->daysLeft),
            'renewUrl' => url_slash('cenovnik'),
            'accountUrl' => url_slash('moj-nalog/firma'),
        ]);
    }

    /** "1 dan", "3 dana", "14 dana", "21 dan". */
    public static function days(int $days): string
    {
        return $days.' '.($days % 10 === 1 && $days % 100 !== 11 ? 'dan' : 'dana');
    }

    private function label(): string
    {
        return FirmPackage::definition($this->firm->free_program ? FirmPackage::FREE : $this->firm->package)['label'] ?? 'za firme';
    }

    private function until(): string
    {
        return $this->firm->package_ends_at->copy()->timezone(config('app.timezone'))->format('j. n. Y.');
    }
}
