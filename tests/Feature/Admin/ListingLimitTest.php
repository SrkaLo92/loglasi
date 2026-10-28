<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Listing;
use App\Models\User;
use App\Support\LikelyFirm;
use App\Support\ListingLimits;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class ListingLimitTest extends TestCase
{
    use ActsAsStaff;

    public function test_admin_gives_a_personal_limit_and_removes_it(): void
    {
        $user = User::factory()->create();
        $first = Listing::factory()->create(['user_id' => $user->id, 'status' => ListingStatus::Published, 'expires_at' => now()->addDays(10)]);
        Listing::factory()->count(59)->sequence(fn ($s) => ['title' => 'Oglas broj '.$s->index])->create([
            'user_id' => $user->id, 'status' => ListingStatus::Published, 'expires_at' => now()->addDays(10),
            'category_id' => $first->category_id, 'location_id' => $first->location_id,
        ]);
        $this->assertFalse(ListingLimits::canCreate($user));
        $this->assertNotSame([], LikelyFirm::reasons($user));
        $this->signIn($this->admin());

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->callAction('listingLimit', ['limit' => 150])
            ->assertHasNoActionErrors();

        $user->refresh();
        $this->assertSame(150, ListingLimits::for($user)['monthly_limit']);
        $this->assertTrue(ListingLimits::canCreate($user));
        $this->assertSame([], LikelyFirm::reasons($user));
        $this->assertFalse(LikelyFirm::scope(User::query())->whereKey($user->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.listing_limit', 'subject_id' => $user->id]);

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])->callAction('listingLimit', ['limit' => null]);
        $this->assertSame(10, ListingLimits::for($user->refresh())['monthly_limit']);
    }

    public function test_a_lower_personal_limit_does_not_cut_the_normal_one(): void
    {
        $user = User::factory()->create(['listing_limit' => 3]);

        $this->assertSame(10, ListingLimits::monthlyLimit($user));
    }

    public function test_moderator_does_not_see_the_action(): void
    {
        $user = User::factory()->create();
        $this->signIn($this->moderator());

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])->assertActionHidden('listingLimit');
    }
}
