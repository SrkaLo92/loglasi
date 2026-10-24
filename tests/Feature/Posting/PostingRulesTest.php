<?php

namespace Tests\Feature\Posting;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Services\ListingImages;
use App\Services\Posting;
use App\Services\PostingRefused;
use App\Support\ListingLimits;
use App\Support\Posting\ListingSlug;
use App\Support\Settings;

class PostingRulesTest extends PostingTestCase
{
    private function submit(Listing $listing): Listing
    {
        return app(Posting::class)->submit($listing, $listing->user);
    }

    // ---------------------------------------------------------------- monthly limit

    public function test_private_user_limit_from_settings_blocks_new_drafts_and_submit(): void
    {
        Settings::set('private_active_limit', 2);
        $user = $this->user();
        $this->published($user, ['title' => 'Prvi oglas']);
        $draft = $this->draft($user, ['title' => 'Drugi oglas']);
        $this->submit($draft);

        $this->actingAs($user)->get('/postavi-oglas/')->assertOk()
            ->assertSee('Sva besplatna mesta su popunjena')
            ->assertSee('Imate već 2 aktivna oglasa')
            ->assertDontSee('Izaberite kategoriju');
        $this->post('/postavi-oglas/', ['category_id' => $this->services->id])->assertSessionHasErrors('limit');

        $another = $this->draft($user, ['title' => 'Treći, ranije započet']);
        $this->post('/postavi-oglas/'.$another->id.'/objavi/')->assertSessionHasErrors('submit');
        $this->assertSame(ListingStatus::Draft, $another->refresh()->status);
    }

    public function test_only_active_listings_use_the_limit(): void
    {
        Settings::set('private_active_limit', 1);
        $user = $this->user();
        $this->draft($user);
        $this->draft($user, ['title' => 'Još jedan nacrt']);
        $this->assertTrue(ListingLimits::canCreate($user));

        $listing = $this->published($user);
        $this->assertFalse(ListingLimits::canCreate($user));

        $listing->delete(); // a deleted listing frees its slot
        $this->assertTrue(ListingLimits::canCreate($user));

        $expired = $this->published($user, ['title' => 'Istekao']);
        $expired->forceFill(['expires_at' => now()->subDay()])->save();
        $this->assertTrue(ListingLimits::canCreate($user));
    }

    public function test_submitting_an_old_draft_counts_in_the_month_of_submitting(): void
    {
        $user = $this->user();
        $draft = $this->draft($user, ['created_at' => now()->subMonths(2)]);
        $this->submit($draft);

        $this->assertTrue($draft->refresh()->created_at->isToday());
        $this->assertSame(1, ListingLimits::activeCount($user));
    }

    public function test_firm_with_package_uses_package_limit(): void
    {
        Settings::set('private_active_limit', 1);
        $firm = $this->user(['account_type' => 'firm']);
        $firm->firm()->create(['name' => 'Auto Plac']);
        $firm->firm->forceFill(['package' => 'start', 'package_period' => 'monthly', 'package_ends_at' => now()->addMonth()])->save();
        $this->published($firm);

        $this->assertTrue(ListingLimits::canCreate($firm->fresh()));

        $firm->firm->forceFill(['package_ends_at' => now()->subDay()])->save();
        $this->expectException(PostingRefused::class);
        app(Posting::class)->ensureCanCreate($firm->fresh());
    }

    // ---------------------------------------------------------------- duplicates

    public function test_similar_title_in_same_category_within_30_days_is_blocked_with_link(): void
    {
        $user = $this->user();
        $existing = $this->published($user, ['title' => 'Prodajem Golf 4 1.9 TDI', 'slug' => 'prodajem-golf-4-1-9-tdi']);
        $draft = $this->draft($user);

        $response = $this->actingAs($user)->put('/postavi-oglas/'.$draft->id.'/osnovno/', [
            'title' => 'Prodajem golf 4 1,9 TDI!', 'description' => 'Isti auto opet.', 'location_id' => $this->loznica->id, 'price_type' => 'negotiable',
        ]);
        $response->assertSessionHasErrors('title')->assertSessionHas('duplicate.url', url_slash('oglas/'.$existing->slug));
        $this->followingRedirects()->put('/postavi-oglas/'.$draft->id.'/osnovno/', [
            'title' => 'Prodajem golf 4 1,9 TDI!', 'description' => 'Isti auto opet.', 'location_id' => $this->loznica->id, 'price_type' => 'negotiable',
        ])->assertSee('href="'.url_slash('oglas/'.$existing->slug).'"', false);
    }

    public function test_duplicate_rules_category_age_and_status(): void
    {
        $user = $this->user();
        $posting = app(Posting::class);
        $probe = fn (string $title) => new Listing(['user_id' => $user->id, 'category_id' => $this->services->id]);

        $this->published($user, ['title' => 'Krečenje stanova', 'category_id' => $this->cars->id]);
        $this->published($user, ['title' => 'Selidbe i prevoz', 'created_at' => now()->subDays(31)]);
        $this->published($user, ['title' => 'Čišćenje dvorišta']);
        $this->published($this->user(), ['title' => 'Košenje trave']);

        $posting->ensureNotDuplicate($probe(''), 'Krečenje stanova');   // other category
        $posting->ensureNotDuplicate($probe(''), 'Selidbe i prevoz');   // older than 30 days
        $posting->ensureNotDuplicate($probe(''), 'Košenje trave');      // other user
        $posting->ensureNotDuplicate($probe(''), 'Popravka bicikala');  // different title

        try {
            $posting->ensureNotDuplicate($probe(''), 'ciscenje dvorista');
            $this->fail('duplicate expected');
        } catch (PostingRefused $e) {
            $this->assertSame('duplicate', $e->reason);
            $this->assertSame('Čišćenje dvorišta', $e->existing->title);
        }
    }

    public function test_expired_duplicate_suggests_renewal(): void
    {
        $user = $this->user();
        $old = $this->published($user, ['title' => 'Prodajem drva za ogrev', 'status' => ListingStatus::Expired, 'expires_at' => now()->subDay()]);
        $draft = $this->draft($user, ['title' => 'Prodajem drva za ogrev']);

        $this->actingAs($user)->post('/postavi-oglas/'.$draft->id.'/objavi/')
            ->assertSessionHasErrors(['submit' => 'Već ste postavili sličan oglas u ovoj kategoriji: „Prodajem drva za ogrev”. Oglas je istekao, obnovite ga besplatno umesto da postavljate novi.'])
            ->assertSessionHas('duplicate.renew', url_slash('moj-nalog/oglasi/'.$old->id.'/obnova'));
    }

    public function test_same_image_in_another_listing_is_blocked(): void
    {
        $user = $this->user();
        $images = app(ListingImages::class);
        $first = $this->published($user, ['title' => 'Bicikl za decu']);
        $images->upload($this->image('stripes.jpg'), $user, $first);
        $draft = $this->draft($user, ['title' => 'Potpuno drugi naslov']);
        $images->upload($this->image('stripes.jpg'), $user, $draft);

        $this->actingAs($user)->post('/postavi-oglas/'.$draft->id.'/objavi/')->assertSessionHasErrors('submit')
            ->assertSessionHas('duplicate.title', 'Bicikl za decu');
        $this->assertSame(ListingStatus::Draft, $draft->refresh()->status);
    }

    // ---------------------------------------------------------------- moderation

    public function test_moderation_none_publishes_at_once(): void
    {
        Settings::set('moderation', 'none');
        $user = $this->user(['created_at' => now(), 'phone_verified_at' => null]);

        $this->assertSame(ListingStatus::Published, $this->submit($this->draft($user))->status);
    }

    public function test_moderation_all_is_the_default_and_covers_the_staff_too(): void
    {
        \App\Models\Setting::query()->where('key', 'moderation')->delete();
        Settings::flush();
        $this->assertSame(ListingStatus::Pending, $this->submit($this->draft($this->user()))->status);
    }

    public function test_moderation_all_sends_everyone_to_review_except_staff_posting_for_others(): void
    {
        Settings::set('moderation', 'all');
        $listing = $this->submit($this->draft($this->user()));
        $this->assertSame(ListingStatus::Pending, $listing->status);
        $this->assertNull($listing->published_at);
        $this->assertNotNull($listing->expires_at);
        $this->assertSame('krecenje-stanova-i-kuca', $listing->slug);

        // KP style: the admin's own listing waits too; submitting someone else's listing approves it.
        $admin = $this->user(['role' => 'admin']);
        $this->assertSame(ListingStatus::Pending, $this->submit($this->draft($admin, ['title' => 'Admin oglas']))->status);
        $other = $this->draft($this->user(), ['title' => 'Oglas za korisnika']);
        $this->assertSame(ListingStatus::Published, app(Posting::class)->submit($other, $admin)->status);
    }

    public function test_moderation_new_users(): void
    {
        Settings::set('moderation', 'new_users');

        $young = $this->user(['created_at' => now()->subDays(6)]);
        $unverified = $this->user(['phone_verified_at' => null]);
        $trusted = $this->user(['created_at' => now()->subDays(8)]);

        $this->assertSame(ListingStatus::Pending, $this->submit($this->draft($young))->status);
        $this->assertSame(ListingStatus::Pending, $this->submit($this->draft($unverified))->status);
        $this->assertSame(ListingStatus::Published, $this->submit($this->draft($trusted))->status);

        $this->actingAs($young)->get('/postavi-oglas/'.$this->draft($young, ['title' => 'Još jedan'])->id.'/pregled/')
            ->assertSee('Oglas će biti vidljiv kad ga pregledamo');
    }

    public function test_activate_sets_publication_and_expiry(): void
    {
        Settings::set('listing_duration_days', 10);
        Settings::set('moderation', 'all');
        $listing = $this->submit($this->draft($this->user()));

        app(Posting::class)->activate($listing);

        $listing->refresh();
        $this->assertSame(ListingStatus::Published, $listing->status);
        $this->assertNotNull($listing->published_at);
        $this->assertTrue($listing->expires_at->between(now()->addDays(10)->subMinute(), now()->addDays(10)->addMinute()));
    }

    // ---------------------------------------------------------------- slug

    public function test_slug_is_transliterated_like_wordpress(): void
    {
        $this->assertSame('cevapi-i-pljeskavice-djuvec', ListingSlug::base('Ćevapi i pljeskavice, đuveč'));
        $this->assertSame('zuta-sljiva-zito', ListingSlug::base('Žuta šljiva / žito'));
        $this->assertSame('golf-4-1-9-tdi', ListingSlug::base('Golf 4 1.9 TDI'));
        $this->assertSame('prodajem-stan-50-m2', ListingSlug::base('Prodajem stan 50 m²'));
        $this->assertSame('djordje-tesla', ListingSlug::base('Ђорђе Тесла'));
        $this->assertSame('oglas', ListingSlug::base('!!!'));
        $this->assertLessThanOrEqual(ListingSlug::MAX_LENGTH, strlen(ListingSlug::base(str_repeat('dugacak naslov ', 30))));
    }

    public function test_slug_is_unique_including_deleted_listings(): void
    {
        $user = $this->user();
        $this->published($user, ['slug' => 'krecenje-stanova-i-kuca', 'title' => 'x', 'category_id' => $this->cars->id]);
        $deleted = $this->published($user, ['slug' => 'krecenje-stanova-i-kuca-2', 'title' => 'y', 'category_id' => $this->cars->id]);
        $deleted->delete();

        $listing = $this->submit($this->draft($user));
        $this->assertSame('krecenje-stanova-i-kuca-3', $listing->slug);
    }

    public function test_slug_with_like_wildcards_is_handled(): void
    {
        $this->published($this->user(), ['slug' => 'a-b-2']);
        $this->assertSame('a-b', ListingSlug::unique('a_b'));
    }
}
