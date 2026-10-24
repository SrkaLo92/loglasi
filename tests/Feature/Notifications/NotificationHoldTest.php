<?php

namespace Tests\Feature\Notifications;

use App\Notifications\Messages\PushMessage;
use App\Support\Push\FcmClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationHoldTest extends TestCase
{
    use FakesFcm;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'array']);
    }

    private function send(string $to): void
    {
        Mail::raw('Tekst', fn ($m) => $m->to($to)->subject('Proba'));
    }

    private function sent(): int
    {
        return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages()->count();
    }

    public function test_without_hold_mail_goes_out(): void
    {
        $this->send('kupac@example.com');

        $this->assertSame(1, $this->sent());
    }

    public function test_hold_drops_mail_to_real_users(): void
    {
        config(['notifications.hold' => true, 'notifications.allow' => ['test@loglasi.rs', '@alphasoft.dev']]);

        $this->send('kupac@example.com');
        $this->assertSame(0, $this->sent());

        $this->send('test@loglasi.rs');
        $this->send('Neko@Alphasoft.dev');
        $this->assertSame(2, $this->sent());
    }

    public function test_hold_drops_push(): void
    {
        $this->configureFcm();
        config(['notifications.hold' => true]);

        $result = app(FcmClient::class)->send('fcm-token-'.str_repeat('a', 30), new PushMessage('Naslov', 'Tekst'));

        $this->assertSame(FcmClient::DISABLED, $result);
        Http::assertNothingSent();
    }
}
