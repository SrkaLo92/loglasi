<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ListingsRelationManager;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Settings;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use ActsAsStaff;

    public function test_verify_phone_in_one_click(): void
    {
        $user = User::factory()->create(['phone' => '+381651234567']);
        $this->signIn($mod = $this->moderator());

        Livewire::withQueryParams(['tab' => 'phone'])->test(ListUsers::class)
            ->assertCanSeeTableRecords([$user])
            ->callTableAction('verifyPhone', $user);

        $this->assertNotNull($user->refresh()->phone_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.verify_phone', 'subject_id' => $user->id, 'user_id' => $mod->id]);
    }

    public function test_verify_phone_is_hidden_without_phone(): void
    {
        $user = User::factory()->create(['phone' => null]);
        $this->signIn($this->admin());

        Livewire::test(ListUsers::class)->assertTableActionHidden('verifyPhone', $user);
    }

    public function test_search_by_local_phone_number(): void
    {
        $user = User::factory()->create(['phone' => '+381655415128', 'name' => 'Petar Petrović']);
        $other = User::factory()->create(['phone' => '+381641111111']);
        $this->signIn($this->admin());

        Livewire::test(ListUsers::class)->searchTable('065 541 5128')
            ->assertCanSeeTableRecords([$user])
            ->assertCanNotSeeTableRecords([$other]);
        Livewire::test(ListUsers::class)->searchTable('Petar')
            ->assertCanSeeTableRecords([$user])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_block_revokes_tokens_and_unblock(): void
    {
        $user = User::factory()->create();
        $user->createToken('app');
        RefreshToken::create(['user_id' => $user->id, 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDays(30)]);
        $this->signIn($this->moderator());

        Livewire::test(ViewUser::class, ['record' => $user->id])->callAction('block');

        $user->refresh();
        $this->assertSame(UserStatus::Blocked, $user->status);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotNull(RefreshToken::first()->revoked_at);

        Livewire::test(ViewUser::class, ['record' => $user->id])->callAction('unblock');
        $this->assertSame(UserStatus::Active, $user->refresh()->status);
        $this->assertSame(['user.block', 'user.unblock'], AuditLog::orderBy('id')->pluck('action')->filter(fn ($a) => str_starts_with($a, 'user.'))->values()->all());
    }

    public function test_moderator_cannot_block_admin_or_change_roles_or_make_firm(): void
    {
        $other = $this->admin();
        $user = User::factory()->create();
        $this->signIn($this->moderator());

        Livewire::test(ViewUser::class, ['record' => $other->id])->assertActionHidden('block');
        Livewire::test(ViewUser::class, ['record' => $user->id])
            ->assertActionHidden('changeRole')
            ->assertActionHidden('makeFirm')
            ->assertActionHidden('grantBiznis');
    }

    public function test_make_firm_creates_profile_and_back_to_private(): void
    {
        $user = User::factory()->create(['name' => 'Marko Marković']);
        $this->signIn($this->admin());

        Livewire::test(ViewUser::class, ['record' => $user->id])->callAction('makeFirm', ['firm_name' => 'Auto Marko']);

        $user->refresh();
        $this->assertSame(AccountType::Firm, $user->account_type);
        $this->assertSame('Auto Marko', $user->firm->name);

        Livewire::test(ViewUser::class, ['record' => $user->id])->callAction('makePrivate');
        $this->assertSame(AccountType::Private, $user->refresh()->account_type);
        $this->assertNotNull(Firm::where('user_id', $user->id)->first());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.make_firm']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.make_private']);
    }

    public function test_grant_biznis_with_founding_counter(): void
    {
        Settings::set('founding_firms_total', 1);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->signIn($this->admin());

        $until = now()->addYear()->startOfMinute();
        Livewire::test(ViewUser::class, ['record' => $first->id])->callAction('grantBiznis', ['until' => $until->format('Y-m-d H:i'), 'founding_free' => true]);

        $firm = $first->refresh()->firm;
        $this->assertSame(AccountType::Firm, $first->account_type);
        $this->assertTrue($firm->founding_free);
        $this->assertTrue($firm->biznis_until->equalTo($until));

        // The only free slot is taken.
        Livewire::test(ViewUser::class, ['record' => $second->id])->callAction('grantBiznis', ['until' => $until->format('Y-m-d H:i'), 'founding_free' => true]);
        $this->assertNull($second->refresh()->firm);

        Livewire::test(ViewUser::class, ['record' => $first->id])->callAction('revokeBiznis');
        $this->assertNull($first->refresh()->firm->biznis_until);
        $this->assertSame(1, AuditLog::where('action', 'user.grant_biznis')->count());
        $this->assertSame(1, AuditLog::where('action', 'user.revoke_biznis')->count());
    }

    public function test_admin_changes_role_but_not_own(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->signIn($admin);

        Livewire::test(ViewUser::class, ['record' => $user->id])->callAction('changeRole', ['role' => 'moderator']);
        $this->assertSame(Role::Moderator, $user->refresh()->role);
        $this->assertSame(['role' => ['user', 'moderator']], AuditLog::where('action', 'user.change_role')->first()->changes);

        Livewire::test(ViewUser::class, ['record' => $admin->id])->assertActionHidden('changeRole');
    }

    public function test_user_page_lists_listings_and_orders(): void
    {
        $user = User::factory()->create();
        Listing::factory()->create(['user_id' => $user->id, 'title' => 'Prodajem kosilicu']);
        $this->signIn($this->admin());

        $this->get('/admin/users/'.$user->id)->assertOk()->assertSee('Oglasi')->assertSee('Narudžbine');
        Livewire::test(ListingsRelationManager::class, ['ownerRecord' => $user, 'pageClass' => ViewUser::class])
            ->assertSee('Prodajem kosilicu');
    }

    public function test_edit_user_is_admin_only_and_logged(): void
    {
        $user = User::factory()->create(['name' => 'Staro Ime']);
        $this->signIn($this->moderator());
        $this->get('/admin/users/'.$user->id.'/edit')->assertForbidden();

        $this->signIn($this->admin());
        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm(['name' => 'Novo Ime', 'phone' => '064 111 2222'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('+381641112222', $user->refresh()->phone);
        $this->assertSame(['Staro Ime', 'Novo Ime'], AuditLog::where('action', 'user.update')->first()->changes['name']);
    }
}
