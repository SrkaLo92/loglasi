<?php

namespace Tests\Feature\Notifications;

use App\Mail\BrandedMailable;
use App\Mail\ListingExpiringSoon;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\Messages\BrandedMailMessage;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MailLayoutTest extends TestCase
{
    private const TAGLINE = 'loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.';

    /** @return list<Email> */
    private function sent(): array
    {
        return array_map(fn ($m) => $m->getOriginalMessage(), iterator_to_array(app('mailer')->getSymfonyTransport()->messages()));
    }

    public function test_category_notification_has_layout_text_part_and_list_unsubscribe(): void
    {
        $user = User::factory()->create(['email' => 'mira@example.com']);
        $user->notify(new class extends Notification
        {
            public function via($n): array
            {
                return ['mail'];
            }

            public function toMail($n): BrandedMailMessage
            {
                return BrandedMailMessage::to($n, NotificationPreferences::NEWSLETTER)
                    ->subject('Novosti')->line('Tekst [link](https://zlo.example) **masno**')->line(BrandedMailMessage::plain('[link](https://zlo.example)'))
                    ->action('Otvori sajt', url_slash('/'));
            }
        });

        [$email] = $this->sent();
        $html = $email->getHtmlBody();
        $text = $email->getTextBody();

        $this->assertSame('loglasi.rs', $email->getFrom()[0]->getName());
        $this->assertStringContainsString('src="'.asset('img/brand/logo.png').'"', $html);
        $this->assertStringContainsString('alt="loglasi.rs"', $html);
        $this->assertStringContainsString(self::TAGLINE, $html);
        $this->assertStringContainsString('#2b7a64', $html); // theme inlined
        $this->assertStringContainsString(url_slash('moj-nalog/obavestenja'), $html);
        $this->assertStringContainsString('Odjavite se od ovih poruka', $html);
        $this->assertStringContainsString('Ako dugme „Otvori sajt” ne radi', $html);
        $this->assertStringNotContainsString('Regards', $html);

        $this->assertNotNull($text);
        $this->assertStringContainsString(self::TAGLINE, $text);
        $this->assertStringContainsString('Odjava od ovih poruka: '.url('/moj-nalog/obavestenja/odjava/'.$user->id.'/newsletter'), $text);

        $header = $email->getHeaders()->get('List-Unsubscribe')->getBodyAsString();
        $this->assertStringStartsWith('<'.url('/moj-nalog/obavestenja/odjava/'.$user->id.'/newsletter').'?signature=', $header);
        $this->assertSame('List-Unsubscribe=One-Click', $email->getHeaders()->get('List-Unsubscribe-Post')->getBodyAsString());
    }

    public function test_escaped_user_text_does_not_become_a_link(): void
    {
        $user = User::factory()->create();
        $html = BrandedMailMessage::to($user)->line(BrandedMailMessage::plain('[klikni](https://zlo.example) # naslov'))->render()->toHtml();

        $this->assertStringNotContainsString('href="https://zlo.example"', $html);
        $this->assertStringContainsString('klikni', $html);
    }

    public function test_account_mail_has_no_unsubscribe_but_is_branded(): void
    {
        $user = User::factory()->unverified()->create();
        $user->notify(new VerifyEmail);

        [$email] = $this->sent();
        $this->assertNull($email->getHeaders()->get('List-Unsubscribe'));
        $this->assertStringContainsString(self::TAGLINE, $email->getHtmlBody());
        $this->assertStringContainsString('Potvrdi email adresu', $email->getHtmlBody());
        $this->assertStringNotContainsString('Odjavite se', $email->getHtmlBody());
        $this->assertStringContainsString(self::TAGLINE, (string) $email->getTextBody());
    }

    public function test_branded_mailable_is_queued_with_headers(): void
    {
        $user = User::factory()->create();
        $mail = new class($user) extends BrandedMailable
        {
            protected ?string $category = NotificationPreferences::EXPIRING;

            public function envelope(): Envelope
            {
                return new Envelope(subject: 'Oglas uskoro ističe');
            }

            public function content(): Content
            {
                return $this->markdownContent('notifications.mail.saved-search-digest', [
                    'searches' => [['id' => 1, 'name' => 'Golf', 'url' => url_slash('oglasi'), 'total' => 1, 'listings' => []]],
                    'total' => 1,
                    'manageUrl' => url_slash('moj-nalog/pretrage'),
                ]);
            }
        };

        $this->assertInstanceOf(ShouldQueue::class, $mail);
        $mail->assertHasTo($user->email);
        $mail->assertSeeInHtml(self::TAGLINE);
        $mail->assertSeeInHtml('Golf');
        $mail->assertSeeInText(self::TAGLINE);
        $mail->assertSeeInText('Odjava od ovih poruka');
        $this->assertStringContainsString('/moj-nalog/obavestenja/odjava/'.$user->id.'/expiring', $mail->headers()->text['List-Unsubscribe']);

        Mail::fake();
        Mail::send($mail);
        Mail::assertQueued($mail::class);
    }

    public function test_listing_expiry_mail_can_be_unsubscribed(): void
    {
        $listing = Listing::factory()->create();
        $mail = new ListingExpiringSoon($listing);

        $this->assertStringContainsString(
            '/moj-nalog/obavestenja/odjava/'.$listing->user_id.'/expiring',
            $mail->headers()->text['List-Unsubscribe'],
        );
    }
}
