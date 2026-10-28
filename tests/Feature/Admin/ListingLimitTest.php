<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Listing;
use App\Models\User;
use App\Services\Admin\UserAdministration;
use App\Services\ListingParking;
use App\Services\Posting;
use App\Services\PostingRefused;
use App\Support\LikelyFirm;
use App\Support\ListingLimits;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class ListingLimitTest extends TestCase
{
    use ActsAsStaff;

    /** @return list<Listing> oldest published first */
    private function activeListings(User $user, int $count): array
    {
        $first = Listing::factory()->create(['user_id' => $user->id, 'status' => ListingStatus::Published,
            'published_at' => now()->subDays(100), 'expires_at' => now()->addDays(10)]);
        $rest = Listing::factory()->count($count - 1)->sequence(fn ($s) => [
            'title' => 'Oglas broj '.$s->index, 'published_at' => now()->subDays(99 - $s->index),
        ])->create([
            'user_id' => $user->id, 'status' => ListingStatus::Published, 'expires_at' => now()->addDays(10),
            'category_id' => $first->category_id, 'location_id' => $first->location_id,
        ]);

        return [$first, ...$rest->all()];
    }

    private function setLimit(User $user, string $mode, ?int $limit = null): void
    {
        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->callAction('listingLimit', ['mode' => $mode, 'limit' => $limit])
            ->assertHasNoActionErrors();
        $user->refresh();
    }

    public function test_admin_gives_a_personal_limit_and_removes_it(): void
    {
        $user = User::factory()->create();
        $this->activeListings($user, 60);
        $this->assertFalse(ListingLimits::canCreate($user));
        $this->assertNotSame([], LikelyFirm::reasons($user));
        $this->signIn($this->admin());

        $this->setLimit($user, 'number', 150);

        $this->assertSame(150, ListingLimits::for($user)['monthly_limit']);
        $this->assertTrue(ListingLimits::canCreate($user));
        $this->assertSame([], LikelyFirm::reasons($user));
        $this->assertFalse(LikelyFirm::scope(User::query())->whereKey($user->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.listing_limit', 'subject_id' => $user->id]);
    }

    public function test_no_limit_then_back_to_normal_parks_the_oldest_and_restores_them(): void
    {
        $user = User::factory()->create();
        $listings = $this->activeListings($user, 14);
        $this->signIn($this->admin());

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])->mountAction('listingLimit')
            ->assertActionMounted('listingLimit')->assertSchemaStateSet(['mode' => 'standard'], 'mountedActionSchema0');
        $this->setLimit($user, 'unlimited');
        $this->assertTrue(ListingLimits::for($user)['unlimited']);
        $this->assertTrue(ListingLimits::canCreate($user));
        $this->assertSame([], LikelyFirm::reasons($user));

        $this->assertSame(4, UserAdministration::limitPreview($user, 'standard', null));
        $this->setLimit($user, 'standard');

        $this->assertSame(10, ListingLimits::activeCount($user));
        $parked = Listing::query()->where('user_id', $user->id)->where('status', ListingStatus::Draft)->pluck('id')->sort()->values()->all();
        $this->assertSame(array_map(fn ($l) => $l->id, array_slice($listings, 0, 4)), $parked);
        $this->assertNotNull($listings[0]->refresh()->parked_at);
        $this->assertSame(0, app(Posting::class)->pruneDrafts());

        // No room: nothing comes back.
        Livewire::test(ViewUser::class, ['record' => $user->getKey()])->callAction('restoreParked');
        $this->assertSame(4, app(ListingParking::class)->parkedCount($user));

        // Room again (bigger limit): they come back with the same address, without review.
        $slug = $listings[0]->slug;
        $this->setLimit($user, 'number', 12);
        Livewire::test(ViewUser::class, ['record' => $user->getKey()])->callAction('restoreParked');
        $this->assertSame(12, ListingLimits::activeCount($user));
        $this->assertSame(2, app(ListingParking::class)->parkedCount($user));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.restore_parked', 'subject_id' => $user->id]);

        // The owner can bring one back by submitting the draft once a slot frees.
        $listings[13]->refresh();
        app(Posting::class)->markSold($listings[13]);
        $draft = Listing::query()->where('user_id', $user->id)->whereNotNull('parked_at')->firstOrFail();
        $slug = $draft->slug;
        app(Posting::class)->submit($draft, $user);
        $this->assertSame(ListingStatus::Published, $draft->refresh()->status);
        $this->assertSame($slug, $draft->slug);
        $this->assertNull($draft->parked_at);
    }

    public function test_renewing_an_expired_listing_respects_the_limit(): void
    {
        $user = User::factory()->create();
        $listings = $this->activeListings($user, 11);
        $listings[0]->update(['status' => ListingStatus::Expired, 'expires_at' => now()->subDay()]);

        $this->expectException(PostingRefused::class);
        app(Posting::class)->renew($listings[0]->refresh());
    }

    public function test_a_lower_personal_limit_does_not_cut_the_normal_one(): void
    {
        $user = User::factory()->create(['listing_limit' => 3]);

        $this->assertSame(10, ListingLimits::monthlyLimit($user));
    }

    public function test_moderator_does_not_see_the_actions(): void
    {
        $user = User::factory()->create();
        $this->signIn($this->moderator());

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])->assertActionHidden('listingLimit')->assertActionHidden('restoreParked');
    }
}
