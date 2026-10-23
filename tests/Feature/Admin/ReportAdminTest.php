<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class ReportAdminTest extends TestCase
{
    use ActsAsStaff;

    private function report($target, string $reason = 'prevara'): Report
    {
        return Report::create([
            'reporter_id' => User::factory()->create()->id,
            'reportable_type' => $target->getMorphClass(),
            'reportable_id' => $target->id,
            'reason' => $reason,
            'details' => 'Traži uplatu unapred.',
        ]);
    }

    public function test_resolve_and_dismiss(): void
    {
        $a = $this->report(Listing::factory()->create());
        $b = $this->report(Listing::factory()->create());
        $this->signIn($mod = $this->moderator());

        Livewire::test(ListReports::class)
            ->assertCanSeeTableRecords([$a, $b])
            ->callTableAction('resolve', $a)
            ->callTableAction('dismiss', $b);

        $this->assertSame(ReportStatus::Resolved, $a->refresh()->status);
        $this->assertSame($mod->id, $a->resolved_by);
        $this->assertSame(ReportStatus::Dismissed, $b->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.resolve', 'subject_id' => $a->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.dismiss', 'subject_id' => $b->id]);
    }

    public function test_quick_reject_listing_closes_all_its_reports(): void
    {
        Mail::fake();
        $listing = Listing::factory()->create();
        $first = $this->report($listing);
        $second = $this->report($listing, 'duplikat');
        $this->signIn($this->admin());

        Livewire::test(ListReports::class)->callTableAction('rejectListing', $first, ['reason' => 'Prevara.']);

        $this->assertSame(ListingStatus::Rejected, $listing->refresh()->status);
        $this->assertSame(ReportStatus::Resolved, $first->refresh()->status);
        $this->assertSame(ReportStatus::Resolved, $second->refresh()->status);
    }

    public function test_quick_block_listing_owner(): void
    {
        $listing = Listing::factory()->create();
        $report = $this->report($listing);
        $this->signIn($this->moderator());

        Livewire::test(ListReports::class)->callTableAction('blockUser', $report);

        $this->assertSame(UserStatus::Blocked, $listing->user->refresh()->status);
        $this->assertSame(ReportStatus::Resolved, $report->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.block', 'subject_id' => $listing->user_id]);
    }

    public function test_quick_delete_listing_and_reported_user(): void
    {
        $listing = Listing::factory()->create();
        $report = $this->report($listing);
        $userReport = $this->report($user = User::factory()->create(), 'neprikladno');
        $this->signIn($this->admin());

        Livewire::test(ListReports::class)->callTableAction('deleteListing', $report);
        $this->assertSoftDeleted($listing);
        $this->assertSame(ReportStatus::Resolved, $report->refresh()->status);

        Livewire::test(ListReports::class)->callTableAction('blockUser', $userReport);
        $this->assertSame(UserStatus::Blocked, $user->refresh()->status);
    }
}
