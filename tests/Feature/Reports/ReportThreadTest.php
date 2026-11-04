<?php

namespace Tests\Feature\Reports;

use App\Enums\ReportStatus;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportQuestionNotification;
use App\Services\Notifications\InAppNotifications;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class ReportThreadTest extends TestCase
{
    use ActsAsStaff;

    private function report(?User $reporter = null): Report
    {
        $listing = Listing::factory()->create();

        return Report::create([
            'reporter_id' => ($reporter ?? User::factory()->create())->id,
            'reportable_type' => $listing->getMorphClass(),
            'reportable_id' => $listing->id,
            'reason' => 'prevara',
            'details' => 'Traži uplatu unapred.',
        ]);
    }

    public function test_admin_asks_the_reporter_who_gets_bell_and_mail(): void
    {
        Notification::fake();
        $report = $this->report();
        $this->signIn($this->moderator());

        Livewire::test(ListReports::class)
            ->callTableAction('conversation', $report, ['body' => 'Šta tačno nije u redu? Da li ste zvali prodavca?'])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('report_messages', ['report_id' => $report->id, 'from_staff' => true, 'body' => 'Šta tačno nije u redu? Da li ste zvali prodavca?']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $report->reporter_id, 'type' => InAppNotifications::REPORT_QUESTION]);
        Notification::assertSentTo($report->reporter, ReportQuestionNotification::class);
    }

    public function test_admin_question_is_required(): void
    {
        $report = $this->report();
        $this->signIn($this->moderator());

        Livewire::test(ListReports::class)
            ->callTableAction('conversation', $report, ['body' => ''])
            ->assertHasTableActionErrors(['body' => 'required']);
        $this->assertDatabaseCount('report_messages', 0);
    }

    public function test_reporter_answers_on_the_site_and_staff_sees_it_as_new(): void
    {
        $reporter = User::factory()->create();
        $report = $this->report($reporter);
        $report->messages()->create(['user_id' => null, 'from_staff' => true, 'body' => 'Koji je problem?']);

        $this->actingAs($reporter)->get('/moj-nalog/prijave/'.$report->id.'/')
            ->assertOk()->assertSee('Koji je problem?')->assertSee('Traži uplatu unapred.');

        $this->actingAs($reporter)->post('/moj-nalog/prijave/'.$report->id.'/', ['body' => 'Traži da pošaljem novac pre pregleda.'])
            ->assertRedirect();
        $this->assertDatabaseHas('report_messages', ['report_id' => $report->id, 'from_staff' => false, 'user_id' => $reporter->id]);
        $this->assertTrue($report->refresh()->staff_unread);

        $this->signIn($this->moderator());
        Livewire::test(ListReports::class)->mountTableAction('conversation', $report);
        $this->assertFalse($report->refresh()->staff_unread);
    }

    public function test_other_users_and_guests_cannot_see_a_report(): void
    {
        $report = $this->report();

        $this->get('/moj-nalog/prijave/'.$report->id.'/')->assertRedirect();
        $this->actingAs(User::factory()->create())->get('/moj-nalog/prijave/'.$report->id.'/')->assertNotFound();
        $this->actingAs(User::factory()->create())->post('/moj-nalog/prijave/'.$report->id.'/', ['body' => 'Upad'])->assertNotFound();
        $this->assertDatabaseCount('report_messages', 0);
    }

    public function test_closed_report_takes_no_answer(): void
    {
        $reporter = User::factory()->create();
        $report = $this->report($reporter);
        $report->forceFill(['status' => ReportStatus::Resolved])->save();

        $this->actingAs($reporter)->post('/moj-nalog/prijave/'.$report->id.'/', ['body' => 'Još nešto'])->assertSessionHasErrors('body');
        $this->assertDatabaseCount('report_messages', 0);
    }

    public function test_app_reads_and_answers_the_thread(): void
    {
        $reporter = User::factory()->create();
        $report = $this->report($reporter);
        $report->messages()->create(['user_id' => null, 'from_staff' => true, 'body' => 'Koji je problem?']);

        $this->getJson('/api/v1/me/reports/'.$report->id)->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/me/reports/'.$report->id)->assertNotFound();

        Sanctum::actingAs($reporter);
        $this->getJson('/api/v1/me/reports/'.$report->id)->assertOk()
            ->assertJsonPath('data.is_open', true)
            ->assertJsonPath('data.target.type', 'listing')
            ->assertJsonPath('data.messages.0.from_staff', true);

        $this->postJson('/api/v1/me/reports/'.$report->id.'/messages', ['body' => ''])->assertStatus(422);
        $this->postJson('/api/v1/me/reports/'.$report->id.'/messages', ['body' => 'Traži avans.'])->assertCreated()
            ->assertJsonPath('data.messages.1.body', 'Traži avans.')
            ->assertJsonPath('data.messages.1.from_staff', false);
    }
}
