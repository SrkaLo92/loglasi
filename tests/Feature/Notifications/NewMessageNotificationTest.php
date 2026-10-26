<?php

namespace Tests\Feature\Notifications;

use App\Models\Device;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Services\Messages\Messenger;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class NewMessageNotificationTest extends TestCase
{
    use FakesFcm;

    private User $seller;

    private User $buyer;

    private Listing $listing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seller = User::factory()->create(['name' => 'Milan Prodavac']);
        $this->buyer = User::factory()->create(['name' => 'Ana Kupac']);
        $this->listing = Listing::factory()->create(['user_id' => $this->seller->id, 'title' => 'Golf 7']);
    }

    /** @return list<Email> */
    private function mails(): array
    {
        return array_map(fn ($m) => $m->getOriginalMessage(), iterator_to_array(app('mailer')->getSymfonyTransport()->messages()));
    }

    public function test_first_message_mails_the_seller(): void
    {
        app(Messenger::class)->start($this->buyer, $this->listing, 'Da li je auto još na prodaju? [klik](https://zlo.example)');

        $mails = $this->mails();
        $this->assertCount(1, $mails);
        $mail = $mails[0];
        $this->assertSame($this->seller->email, $mail->getTo()[0]->getAddress());
        $this->assertSame('Nova poruka od korisnika Ana Kupac', $mail->getSubject());
        $this->assertStringContainsString('Ana Kupac vam je pisao o oglasu „Golf 7”', $mail->getHtmlBody());
        $this->assertStringContainsString('Da li je auto još na prodaju?', $mail->getHtmlBody());
        $this->assertStringNotContainsString('href="https://zlo.example"', $mail->getHtmlBody());
        $this->assertMatchesRegularExpression('#/moj-nalog/poruke/\d+/#', $mail->getHtmlBody());
        $this->assertStringContainsString('/obavestenja/odjava/'.$this->seller->id.'/messages', $mail->getHeaders()->get('List-Unsubscribe')->getBodyAsString());
        $this->assertStringContainsString('Da li je auto još na prodaju?', (string) $mail->getTextBody());
    }

    public function test_active_conversation_is_mailed_once_per_window(): void
    {
        $messenger = app(Messenger::class);
        $message = $messenger->start($this->buyer, $this->listing, 'Prva poruka');
        $conversation = $message->conversation;
        $messenger->reply($this->buyer, $conversation, 'Druga poruka');
        $messenger->reply($this->buyer, $conversation, 'Treća poruka');
        $this->assertCount(1, $this->mails());

        // The seller answers: the buyer gets his own first mail.
        $messenger->reply($this->seller, $conversation, 'Jeste, javite se');
        $this->assertCount(2, $this->mails());
        $this->assertSame($this->buyer->email, $this->mails()[1]->getTo()[0]->getAddress());

        $this->travel(16)->minutes();
        $messenger->reply($this->buyer, $conversation, 'Može sutra?');
        $this->assertCount(3, $this->mails());
    }

    public function test_no_mail_when_the_conversation_was_read_meanwhile(): void
    {
        $messenger = app(Messenger::class);
        $message = $messenger->start($this->buyer, $this->listing, 'Prva poruka');
        $messenger->markRead($this->seller, $message->conversation->fresh());

        $notification = new NewMessageNotification($message->fresh());
        $this->assertFalse($notification->shouldSend($this->seller->fresh(), 'mail'));
        $this->assertTrue($notification->shouldSend($this->seller->fresh(), 'push'));
    }

    public function test_preference_off_sends_nothing(): void
    {
        Notification::fake();
        NotificationPreferences::set($this->seller, ['messages' => false]);

        app(Messenger::class)->start($this->buyer, $this->listing, 'Zdravo');

        Notification::assertNothingSent();
    }

    public function test_push_to_the_recipient_devices(): void
    {
        $this->configureFcm();
        Device::create(['user_id' => $this->seller->id, 'token' => str_repeat('s', 40), 'platform' => 'android']);
        Device::create(['user_id' => $this->buyer->id, 'token' => str_repeat('b', 40), 'platform' => 'android']);

        $message = app(Messenger::class)->start($this->buyer, $this->listing, 'Da li je auto još na prodaju?');

        $sends = Http::recorded(fn (Request $r) => str_contains($r->url(), 'fcm.googleapis.com'));
        $this->assertCount(1, $sends);
        [$send] = $sends->first();
        $this->assertSame(str_repeat('s', 40), $send['message']['token']);
        $this->assertSame(['title' => 'Ana Kupac', 'body' => 'Da li je auto još na prodaju?'], $send['message']['notification']);
        $this->assertSame([
            'type' => 'message',
            'conversation_id' => (string) $message->conversation_id,
            'message_id' => (string) $message->id,
            'title' => 'Ana Kupac',
            'body' => 'Da li je auto još na prodaju?',
        ], (array) $send['message']['data']);
        $this->assertSame('poruke', $send['message']['android']['notification']['channel_id']);
    }

    public function test_unverified_email_gets_push_only(): void
    {
        $this->seller->forceFill(['email_verified_at' => null])->save();
        app(Messenger::class)->start($this->buyer, $this->listing, 'Zdravo');

        $this->assertCount(0, $this->mails());
    }
}
