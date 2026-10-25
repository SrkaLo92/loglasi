<?php

namespace Tests\Feature\Notifications;

use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Admin\ListingModeration;
use App\Services\Notifications\InAppNotifications;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InAppNotificationsTest extends TestCase
{
    public function test_a_save_tells_the_owner_once_and_the_owners_own_save_tells_nobody(): void
    {
        $owner = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $owner->id, 'title' => 'Golf 4']);
        $saver = User::factory()->create();

        $this->actingAs($saver)->post('/moj-nalog/omiljeni/'.$listing->id.'/');
        $this->actingAs($saver)->post('/moj-nalog/omiljeni/'.$listing->id.'/'); // removed
        $this->actingAs($saver)->post('/moj-nalog/omiljeni/'.$listing->id.'/'); // saved again
        $this->actingAs($owner)->post('/moj-nalog/omiljeni/'.$listing->id.'/');

        $this->assertSame(1, UserNotification::query()->where('user_id', $owner->id)->where('type', InAppNotifications::FAVORITE)->count());
        $this->assertSame(0, UserNotification::query()->where('user_id', $saver->id)->count());

        $this->actingAs($owner)->get('/moj-nalog/obavestenja/')->assertOk()
            ->assertSee('Neko je sačuvao vaš oglas')
            ->assertSee('„Golf 4”', false)
            ->assertSee('lgo-item is-new', false);
        $this->assertSame(0, app(InAppNotifications::class)->unread($owner));
    }

    public function test_approval_rejection_and_saved_search_matches_are_listed(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $moderation = app(ListingModeration::class);
        $moderation->approve(Listing::factory()->pending()->create(['user_id' => $owner->id, 'title' => 'Stan 50 m2']));
        $moderation->reject(Listing::factory()->pending()->create(['user_id' => $owner->id, 'title' => 'Bicikl']), 'Nedostaje slika.');
        $search = SavedSearch::query()->create(['user_id' => $owner->id, 'name' => 'Golf do 3000', 'query' => ['keyword' => 'golf'], 'notify' => true]);
        app(InAppNotifications::class)->savedSearchMatches($owner, $search, 3);

        $this->actingAs($owner)->get('/moj-nalog/obavestenja/')->assertOk()
            ->assertSee('Vaš oglas je objavljen')
            ->assertSee('Vaš oglas nije odobren')
            ->assertSee('Nedostaje slika.')
            ->assertSee('3 nova oglasa za vašu pretragu');
    }

    public function test_header_bell_shows_the_unread_count(): void
    {
        $owner = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $owner->id]);
        app(InAppNotifications::class)->favorited($listing, User::factory()->create());
        app(InAppNotifications::class)->favorited($listing, User::factory()->create());

        $this->actingAs($owner)->get('/oglasi/')->assertOk()
            ->assertSee('aria-label="Obaveštenja, 2 novih"', false)
            ->assertSee('<span class="lg-header__broj" aria-hidden="true">2</span>', false);
        auth()->logout();
        $this->get('/')->assertDontSee('lg-header__zvono', false);
    }

    public function test_api_lists_counts_and_marks_read(): void
    {
        $owner = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $owner->id, 'title' => 'Traktor']);
        foreach (range(1, 3) as $i) {
            app(InAppNotifications::class)->favorited($listing, User::factory()->create());
        }
        Sanctum::actingAs($owner);

        $first = $this->getJson('/api/v1/me/notifications?per_page=2')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('unread', 3)
            ->assertJsonPath('data.0.type', 'favorite')
            ->assertJsonPath('data.0.title', 'Neko je sačuvao vaš oglas')
            ->assertJsonPath('data.0.listing_id', $listing->id)
            ->assertJsonPath('data.0.read', false)
            ->assertJsonStructure(['data' => [['id', 'type', 'title', 'body', 'url', 'listing_id', 'saved_search_id', 'image', 'read', 'created_at']], 'next_before', 'unread']);
        $next = $first->json('next_before');
        $this->getJson('/api/v1/me/notifications?before='.$next)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('next_before', null);

        $this->postJson('/api/v1/me/notifications/read', ['ids' => [$first->json('data.0.id')]])->assertOk()->assertJsonPath('unread', 2);
        $this->getJson('/api/v1/me/notifications/unread-count')->assertOk()->assertJsonPath('unread', 2);
        $this->postJson('/api/v1/me/notifications/read')->assertOk()->assertJsonPath('unread', 0);
        $this->getJson('/api/v1/me/overview')->assertOk()->assertJsonPath('counts.notifications', 0);
    }

    public function test_someone_elses_notifications_cannot_be_read_or_marked(): void
    {
        $owner = User::factory()->create();
        app(InAppNotifications::class)->favorited(Listing::factory()->create(['user_id' => $owner->id]), User::factory()->create());
        $id = UserNotification::query()->value('id');
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/me/notifications')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/me/notifications/read', ['ids' => [$id]])->assertOk()->assertJsonPath('marked', 0);
        $this->assertSame(1, app(InAppNotifications::class)->unread($owner));
    }

    public function test_settings_moved_under_the_list_and_old_notifications_are_pruned(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/moj-nalog/obavestenja/podesavanja/')->assertOk()->assertSee('Podešavanja obaveštenja');
        $this->actingAs($user)->get('/moj-nalog/obavestenja/')->assertOk()->assertSee('Još nemate obaveštenja')
            ->assertSee(url_slash('moj-nalog/obavestenja/podesavanja'), false);

        UserNotification::query()->create(['user_id' => $user->id, 'type' => 'favorite', 'created_at' => now()->subDays(InAppNotifications::KEEP_DAYS + 1)]);
        UserNotification::query()->create(['user_id' => $user->id, 'type' => 'favorite', 'created_at' => now()]);
        $this->artisan('notifications:prune-in-app')->assertSuccessful();
        $this->assertSame(1, UserNotification::query()->count());
    }
}
