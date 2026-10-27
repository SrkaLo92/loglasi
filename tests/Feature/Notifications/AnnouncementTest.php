<?php

namespace Tests\Feature\Notifications;

use App\Enums\UserStatus;
use App\Mail\Announcement;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    private const CAMPAIGN = 'novi-sajt-2026-10';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_queues_once_per_user_and_skips_blocked_unverified_and_unsubscribed(): void
    {
        $ok = User::factory()->create(['email' => 'ana@gmail.com']);
        $optedIn = User::factory()->create(['email' => 'bora@gmail.com', 'notification_settings' => ['newsletter' => true]]);
        User::factory()->create(['email' => 'blokiran@gmail.com', 'status' => UserStatus::Blocked]);
        User::factory()->unverified()->create(['email' => 'nepotvrdjen@gmail.com']);
        User::factory()->create(['email' => 'odjavljen@gmail.com', 'notification_settings' => ['newsletter' => false]]);
        User::factory()->create(['email' => 'test@example.com']);

        $this->artisan('mail:announce', ['campaign' => self::CAMPAIGN, '--dry-run' => true])->expectsOutput('Primalaca: 2')->assertSuccessful();
        Mail::assertNothingQueued();

        $this->artisan('mail:announce', ['campaign' => self::CAMPAIGN])->assertSuccessful();
        Mail::assertQueuedCount(2);
        Mail::assertQueued(Announcement::class, fn (Announcement $m) => $m->hasTo($ok->email));
        Mail::assertQueued(Announcement::class, fn (Announcement $m) => $m->hasTo($optedIn->email));

        $this->artisan('mail:announce', ['campaign' => self::CAMPAIGN])->expectsOutput('U redu za slanje: 0 (oko 0 min).');
        Mail::assertQueuedCount(2);
    }

    public function test_test_copies_go_only_to_given_addresses_and_are_not_recorded(): void
    {
        User::factory()->create();

        $this->artisan('mail:announce', ['campaign' => self::CAMPAIGN, '--test' => ['kontakt@loglasi.rs']])->assertSuccessful();

        Mail::assertSent(Announcement::class, fn (Announcement $m) => $m->hasTo('kontakt@loglasi.rs'));
        Mail::assertSentCount(1);
        $this->assertDatabaseCount('announcement_sends', 0);
    }

    public function test_mail_has_links_unsubscribe_and_reply_to(): void
    {
        $user = User::factory()->create(['name' => 'Milan']);
        $mail = new Announcement($user, self::CAMPAIGN);

        $this->assertSame('Osvežili smo loglasi.rs', $mail->envelope()->subject);
        $mail->assertHasReplyTo('kontakt@loglasi.rs');
        $mail->assertSeeInHtml('Zdravo Milan,');
        $mail->assertSeeInHtml('src="'.asset('img/brand/logo-mail.png').'"', false);
        $mail->assertSeeInHtml(url_slash('postavi-oglas'), false);
        $mail->assertSeeInText(url_slash('zaboravljena-lozinka'));
        $mail->assertSeeInText('/moj-nalog/obavestenja/odjava/'.$user->id.'/newsletter');
        $this->assertArrayHasKey('List-Unsubscribe', $mail->headers()->text);
    }

    public function test_unknown_campaign_fails(): void
    {
        $this->artisan('mail:announce', ['campaign' => 'nema'])->assertFailed();
    }
}
