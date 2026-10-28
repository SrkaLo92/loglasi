<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class DeleteUserTest extends TestCase
{
    use ActsAsStaff;

    public function test_admin_deletes_an_account_after_typing_the_email(): void
    {
        $user = User::factory()->create(['email' => 'pera@gmail.com']);
        $listing = Listing::factory()->create(['user_id' => $user->id, 'status' => ListingStatus::Published]);
        $this->signIn($this->admin());

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->callAction('deleteAccount', ['reason' => 'Zahtev korisnika', 'confirm_email' => 'pogresno@gmail.com'])
            ->assertHasActionErrors(['confirm_email']);
        $this->assertNotSoftDeleted($user);

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->callAction('deleteAccount', ['reason' => 'Zahtev korisnika', 'confirm_email' => 'PERA@gmail.com '])
            ->assertHasNoActionErrors()
            ->assertRedirect();

        $this->assertSoftDeleted($user);
        $this->assertSoftDeleted($listing);
        $this->assertSame('Obrisan korisnik', User::withTrashed()->find($user->id)->name);
        $log = AuditLog::query()->where('action', 'user.delete')->where('subject_id', $user->id)->firstOrFail();
        $this->assertSame('Zahtev korisnika', $log->changes['reason']);
    }

    public function test_staff_and_moderators_cannot(): void
    {
        $mod = $this->moderator();
        $user = User::factory()->create();

        $this->signIn($this->admin());
        Livewire::test(ViewUser::class, ['record' => $mod->getKey()])->assertActionHidden('deleteAccount');

        $this->signIn($mod);
        Livewire::test(ViewUser::class, ['record' => $user->getKey()])->assertActionHidden('deleteAccount');
    }
}
