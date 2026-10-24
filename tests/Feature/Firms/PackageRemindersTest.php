<?php

namespace Tests\Feature\Firms;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Mail\Firms\PackageExpiringSoon;
use App\Models\Firm;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PackageRemindersTest extends TestCase
{
    private function firmUser(string $until, array $user = [], string $period = 'yearly'): User
    {
        $account = User::factory()->create(['account_type' => AccountType::Firm, 'email' => 'firma'.uniqid().'@example.com'] + $user);
        Firm::factory()->package('pro', $period)->for($account)->create(['name' => 'Auto servis Drina', 'package_ends_at' => $until]);

        return $account->load('firm');
    }

    public function test_sends_14_and_3_day_reminders_once_each(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-23 09:00:00');
        $user = $this->firmUser('2026-11-06 12:00:00'); // 14 days

        $this->artisan('firms:package-reminders')->assertSuccessful();
        Mail::assertQueued(PackageExpiringSoon::class, fn ($mail) => $mail->hasTo($user->email) && $mail->daysLeft === 14);
        Mail::assertQueuedCount(1);

        $this->artisan('firms:package-reminders');
        $this->travelTo('2026-10-28 09:00:00'); // 9 days: nothing new
        $this->artisan('firms:package-reminders');
        Mail::assertQueuedCount(1);

        $this->travelTo('2026-11-03 09:00:00'); // 3 days
        $this->artisan('firms:package-reminders');
        Mail::assertQueuedCount(2);
        Mail::assertQueued(PackageExpiringSoon::class, fn ($mail) => $mail->daysLeft === 3);

        $this->travelTo('2026-11-04 09:00:00');
        $this->artisan('firms:package-reminders');
        Mail::assertQueuedCount(2);
    }

    public function test_missed_runs_send_only_the_closest_reminder(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-23 09:00:00');
        $this->firmUser('2026-10-25 12:00:00'); // 2 days, nothing sent before

        $this->artisan('firms:package-reminders');
        Mail::assertQueuedCount(1);
        Mail::assertQueued(PackageExpiringSoon::class, fn ($mail) => $mail->daysLeft === 2);
        $this->artisan('firms:package-reminders');
        Mail::assertQueuedCount(1);
    }

    public function test_renewal_starts_the_series_again(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-23 09:00:00');
        $user = $this->firmUser('2026-11-06 12:00:00');
        $this->artisan('firms:package-reminders');

        $user->firm->forceFill(['package_ends_at' => '2027-11-06 12:00:00'])->save(); // renewed
        $this->travelTo('2027-10-23 09:00:00');
        $this->artisan('firms:package-reminders');

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

        $this->artisan('firms:package-reminders');
        Mail::assertNothingQueued();
    }

    public function test_mail_text_is_serbian_and_links_to_renewal(): void
    {
        $this->travelTo('2026-10-23 09:00:00');
        $user = $this->firmUser('2026-10-26 12:00:00');
        $mail = new PackageExpiringSoon($user->firm, 3);

        $mail->assertHasSubject('Paket Business Pro ističe za 3 dana');
        $mail->assertSeeInText('Paket Business Pro firme Auto servis Drina na loglasi.rs važi još 3 dana, do 26. 10. 2026.');
        $mail->assertSeeInText('Pretplata se ne obnavlja sama.');
        $mail->assertDontSeeInText('Founder');
        $mail->assertSeeInText(url_slash('cenovnik'));
        $mail->assertSeeInText(url_slash('moj-nalog/firma'));
        $this->assertSame('Paket Business Pro ističe 26. 10. 2026.', (new PackageExpiringSoon($user->firm, 14))->envelope()->subject);
        $this->assertSame('1 dan', PackageExpiringSoon::days(1));
        $this->assertSame('21 dan', PackageExpiringSoon::days(21));
    }

    public function test_monthly_terms_get_only_the_last_reminder(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-23 09:00:00');
        $this->firmUser('2026-11-06 12:00:00', [], 'monthly'); // 14 days

        $this->artisan('firms:package-reminders');
        Mail::assertNothingQueued();

        $this->travelTo('2026-11-03 09:00:00');
        $this->artisan('firms:package-reminders');
        Mail::assertQueuedCount(1);
    }

    public function test_free_year_mail_says_there_is_no_automatic_billing(): void
    {
        $this->travelTo('2026-10-23 09:00:00');
        $account = User::factory()->create(['account_type' => AccountType::Firm]);
        $firm = Firm::factory()->freeYear()->for($account)->create(['name' => 'Pekara', 'package_ends_at' => '2026-11-06 12:00:00']);
        $mail = new PackageExpiringSoon($firm, 14);

        $mail->assertHasSubject('Besplatna godina ističe 6. 11. 2026.');
        $mail->assertSeeInText('Besplatna prva godina firme Pekara na loglasi.rs (prvih 30 firmi) važi još 14 dana');
        $mail->assertSeeInText('nema automatske naplate');
    }

    public function test_command_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'firms:package-reminders'));

        $this->assertCount(1, $events);
        $this->assertSame('0 9 * * *', $events->first()->expression);
    }
}
