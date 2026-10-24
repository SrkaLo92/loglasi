<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Filament\Resources\Firms\Pages\EditFirm;
use App\Filament\Resources\Firms\Pages\ListFirms;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\User;
use App\Support\Firms\FirmPackage;
use App\Support\Settings;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class FirmAdminTest extends TestCase
{
    use ActsAsStaff;

    private function firm(array $user = []): Firm
    {
        return Firm::factory()->for(User::factory()->create($user + ['account_type' => AccountType::Firm]))->create();
    }

    public function test_grant_package_and_extend_it(): void
    {
        $firm = $this->firm(['account_type' => AccountType::Private]);
        $this->signIn($this->admin());

        Livewire::test(EditFirm::class, ['record' => $firm->id])
            ->callAction('grantPackage', ['package' => 'pro', 'period' => 'yearly'])
            ->assertHasNoActionErrors();

        $firm->refresh();
        $this->assertSame('pro', FirmPackage::code($firm));
        $this->assertTrue($firm->package_ends_at->between(now()->addYear()->subMinute(), now()->addYear()->addMinute()));
        $this->assertSame(AccountType::Firm, $firm->user->refresh()->account_type);

        // The same package again is added to the running term.
        Livewire::test(EditFirm::class, ['record' => $firm->id])->callAction('grantPackage', ['package' => 'pro', 'period' => 'monthly']);
        $this->assertTrue($firm->refresh()->package_ends_at->between(now()->addYear()->addMonth()->subMinute(), now()->addYear()->addMonth()->addMinute()));
        $this->assertSame(2, AuditLog::where('action', 'firm.grant_package')->where('subject_id', $firm->id)->count());
    }

    public function test_free_year_uses_one_of_the_places(): void
    {
        Settings::set('founding_firms_total', 1);
        $first = $this->firm();
        $second = $this->firm();
        $this->signIn($this->admin());

        Livewire::test(ListFirms::class)->assertSee('slobodno još 1 od 1');

        Livewire::test(EditFirm::class, ['record' => $first->id])->callAction('grantFreeYear');
        $first->refresh();
        $this->assertTrue($first->founding_free);
        $this->assertSame(FirmPackage::FREE, FirmPackage::code($first));
        $this->assertDatabaseHas('audit_logs', ['action' => 'firm.free_year', 'subject_id' => $first->id]);

        // No place left: nothing changes.
        Livewire::test(EditFirm::class, ['record' => $second->id])->callAction('grantFreeYear');
        $this->assertFalse($second->refresh()->founding_free);
        Livewire::test(ListFirms::class)->assertSee('slobodno još 0 od 1')->assertDontSee('Founder');

        Livewire::test(EditFirm::class, ['record' => $first->id])->assertActionHidden('grantFreeYear');
    }

    public function test_end_package(): void
    {
        $firm = $this->firm();
        FirmPackage::grant($firm, 'max', 'monthly');
        $this->signIn($this->admin());

        Livewire::test(EditFirm::class, ['record' => $firm->id])->callAction('endPackage');

        $this->assertFalse(FirmPackage::isActive($firm->refresh()));
        $this->assertDatabaseHas('audit_logs', ['action' => 'firm.end_package', 'subject_id' => $firm->id]);
    }

    public function test_working_hours_are_saved_in_the_shared_shape(): void
    {
        $firm = $this->firm();
        $this->signIn($this->admin());

        Livewire::test(EditFirm::class, ['record' => $firm->id])
            ->fillForm(['working_hours' => [1 => '08–16', 6 => 'Zatvoreno']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['1' => '08–16', '2' => '', '3' => '', '4' => '', '5' => '', '6' => 'Zatvoreno', '7' => ''], $firm->refresh()->working_hours);
    }

    public function test_package_page_shows_credits(): void
    {
        $firm = $this->firm();
        FirmPackage::grant($firm, 'pro', 'monthly');
        $this->signIn($this->admin());

        $this->get('/admin/firms/'.$firm->id.'/edit')->assertOk()->assertSee('Business Pro')->assertSee('Podizanja 10/10');
    }

    public function test_moderator_has_no_access_to_firms(): void
    {
        $firm = $this->firm();
        $this->signIn($this->moderator());

        $this->get('/admin/firms')->assertForbidden();
        $this->get('/admin/firms/'.$firm->id.'/edit')->assertForbidden();
    }
}
