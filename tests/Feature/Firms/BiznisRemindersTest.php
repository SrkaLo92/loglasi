<?php

namespace Tests\Feature\Firms;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Mail\Firms\BiznisExpiringSoon;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BiznisRemindersTest extends TestCase
{
    private function firmUser(string $until, array $user = []): User
    {
        $account = User::factory()->create(['account_type' => AccountType::Firm, 'email' => 'firma'.uniqid().'@example.com'] + $user);
        Firm::factory()->for($account)->create(['name' => 'Auto servis Drina', 'biznis_until' => $until]);

        return $account->load('firm');
    }

    public function test_sends_14_and_3_day_reminders_once_each(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-23 09:00:00');
        $user = $this->firmUser('2026-11-06 12:00:00'); // 14 days

        $this->artisan('firms:biznis-reminders')->assertSuccessful();
        Mail::assertQueued(BiznisExpiringSoon::class, fn ($mail) => $mail->hasTo($user->email) && $mail->daysLeft === 14);
        Mail::assertQueuedCount(1);

        $this->artisan('firms:biznis-reminders');
        $this->travelTo('2026-10-28 09:00:00'); // 9 days: nothing new
        $this->artisan('firms:biznis-reminders');
        Mail::assertQueuedCount(1);

        $this->travelTo('2026-11-03 09:00:00'); // 3 days
        $this->artisan('firms:biznis-reminders');
        Mail::assertQueuedCount(2);
        Mail::assertQueued(BiznisExpiringSoon::class, fn ($mail) => $mail->daysLeft === 3);

        $this->travelTo('2026-11-04 09:00:00');
        $this->artisan('firms:biznis-reminders');
        Mail::assertQueuedCount(2);
    }

    public function test_missed_runs_send_only_the_closest_reminder(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-23 09:00:00');
        $this->firmUser('2026-10-25 12:00:00'); // 2 days, nothing sent before

        $this->artisan('firms:biznis-reminders');
        Mail::assertQueuedCount(1);
        Mail::assertQueued(BiznisExpiringSoon::class, fn ($mail) => $mail->daysLeft === 2);
        $this->artisan('firms:biznis-reminders');
        Mail::assertQueuedCount(1);
    }

    public function test_renewal_starts_the_series_again(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-23 09:00:00');
        $user = $this->firmUser('2026-11-06 12:00:00');
        $this->artisan('firms:biznis-reminders');

        $user->firm->forceFill(['biznis_until' => '2027-11-06 12:00:00'])->save();
        $this->travelTo('2027-10-23 09:00:00');
        $this->artisan('firms:biznis-reminders');

        Mail::assertQueuedCount(2);
    }

    public function test_skips_far_expired_blocked_and_private_accounts(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-23 09:00:00');
        $this->firmUser('2027-01-01 00:00:00');
        $this->firmUser('2026-10-20 00:00:00');
        $this->firmUser('2026-10-26 00:00:00', ['status' => UserStatus::Blocked]);
        $private = $this->firmUser('2026-10-26 00:00:00');
        $private->forceFill(['account_type' => AccountType::Private])->save();

        $this->artisan('firms:biznis-reminders');
        Mail::assertNothingQueued();
    }

    public function test_mail_text_is_serbian_and_links_to_renewal(): void
    {
        $this->travelTo('2026-10-23 09:00:00');
        $user = $this->firmUser('2026-10-26 12:00:00');
        $mail = new BiznisExpiringSoon($user->firm, 3);

        $mail->assertHasSubject('Biznis paket ističe za 3 dana');
        $mail->assertSeeInText('Biznis paket firme Auto servis Drina na loglasi.rs važi još 3 dana, do 26. 10. 2026.');
        $mail->assertSeeInText(url_slash('cenovnik'));
        $mail->assertSeeInText(url_slash('moj-nalog/firma'));
        $this->assertSame('Biznis paket ističe 26. 10. 2026.', (new BiznisExpiringSoon($user->firm, 14))->envelope()->subject);
        $this->assertSame('1 dan', BiznisExpiringSoon::days(1));
        $this->assertSame('21 dan', BiznisExpiringSoon::days(21));
    }

    public function test_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'firms:biznis-reminders'));

        $this->assertCount(1, $events);
        $this->assertSame('0 9 * * *', $events->first()->expression);
    }
}
