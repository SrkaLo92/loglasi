<?php

namespace Tests\Feature\Messages;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FavoritesTest extends TestCase
{
    private function stat(Listing $listing): int
    {
        return (int) DB::table('listing_daily_stats')->where('listing_id', $listing->id)->value('favorites');
    }

    public function test_guest_cannot_save(): void
    {
        $listing = Listing::factory()->create();
        $this->post('/moj-nalog/omiljeni/'.$listing->id.'/')->assertRedirect(url_slash('login-registracija'));
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_save_and_remove_without_js_returns_back(): void
    {
        $listing = Listing::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->from('/oglasi/')->post('/moj-nalog/omiljeni/'.$listing->id.'/')
            ->assertRedirect('/oglasi/')
            ->assertSessionHas('status', 'Oglas je sačuvan u omiljenim.');
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'listing_id' => $listing->id]);
        $this->assertSame(1, $this->stat($listing));

        // The card heart always posts: a second POST removes (toggle), stats are not reduced.
        $this->actingAs($user)->from('/oglasi/')->post('/moj-nalog/omiljeni/'.$listing->id.'/')
            ->assertRedirect('/oglasi/')
            ->assertSessionHas('status', 'Oglas je uklonjen iz omiljenih.');
        $this->assertDatabaseCount('favorites', 0);
        $this->assertSame(1, $this->stat($listing));

        // DELETE always removes and is idempotent.
        $this->actingAs($user)->post('/moj-nalog/omiljeni/'.$listing->id.'/');
        $this->actingAs($user)->from('/oglasi/')->delete('/moj-nalog/omiljeni/'.$listing->id.'/')
            ->assertRedirect('/oglasi/')
            ->assertSessionHas('status', 'Oglas je uklonjen iz omiljenih.');
        $this->actingAs($user)->delete('/moj-nalog/omiljeni/'.$listing->id.'/')->assertRedirect();
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_listing_card_heart_posts_to_the_toggle(): void
    {
        $listing = Listing::factory()->create();
        $user = User::factory()->create();
        DB::table('favorites')->insert(['user_id' => $user->id, 'listing_id' => $listing->id, 'created_at' => now()]);

        $this->actingAs($user)->get('/moj-nalog/omiljeni/')
            ->assertSee('action="'.url_slash('moj-nalog/omiljeni/'.$listing->id).'"', false)
            ->assertSee('aria-pressed="true"', false);
    }

    public function test_json_response_for_scripts(): void
    {
        $listing = Listing::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/moj-nalog/omiljeni/'.$listing->id.'/')->assertOk()->assertJson(['favorite' => true]);
        $this->actingAs($user)->postJson('/moj-nalog/omiljeni/'.$listing->id.'/')->assertOk()->assertJson(['favorite' => false]);
        $this->actingAs($user)->postJson('/moj-nalog/omiljeni/'.$listing->id.'/');
        $this->actingAs($user)->deleteJson('/moj-nalog/omiljeni/'.$listing->id.'/')->assertOk()->assertJson(['favorite' => false]);
        $this->actingAs($user)->postJson('/moj-nalog/omiljeni/999999/')->assertNotFound()->assertJson(['favorite' => false]);
    }

    public function test_only_live_listings_can_be_saved(): void
    {
        $user = User::factory()->create();
        foreach ([Listing::factory()->pending()->create(), Listing::factory()->expired()->create(), tap(Listing::factory()->create())->delete()] as $listing) {
            $this->actingAs($user)->from('/oglasi/')->post('/moj-nalog/omiljeni/'.$listing->id.'/')
                ->assertSessionHas('status', 'Oglas ne postoji ili više nije aktivan.');
        }
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_owner_saving_own_listing_does_not_count(): void
    {
        $listing = Listing::factory()->create();
        $this->actingAs($listing->user)->post('/moj-nalog/omiljeni/'.$listing->id.'/');
        $this->assertDatabaseCount('favorites', 1);
        $this->assertSame(0, $this->stat($listing));
    }

    public function test_favorites_page_hides_deleted_and_marks_expired(): void
    {
        $user = User::factory()->create();
        $live = Listing::factory()->create(['title' => 'Aktivan oglas']);
        $expired = Listing::factory()->create(['title' => 'Istekao oglas']);
        $deleted = Listing::factory()->create(['title' => 'Obrisan oglas']);
        $pending = Listing::factory()->create(['title' => 'Oglas na čekanju']);
        $sold = Listing::factory()->create(['title' => 'Prodat oglas']);
        foreach ([$live, $expired, $deleted, $pending, $sold] as $i => $listing) {
            DB::table('favorites')->insert(['user_id' => $user->id, 'listing_id' => $listing->id, 'created_at' => now()->subMinutes($i)]);
        }
        $expired->update(['expires_at' => now()->subDay()]);
        $deleted->delete();
        $pending->update(['status' => 'pending']);
        $sold->update(['status' => 'sold']);

        $this->actingAs($user)->get('/moj-nalog/omiljeni/')
            ->assertOk()
            ->assertSee('<h1>Omiljeni oglasi</h1>', false)
            ->assertSee('Aktivan oglas')
            ->assertSee('Istekao oglas')
            ->assertSee('Istekao</span>', false)
            ->assertSee('Prodat oglas')
            ->assertSee('Prodato</span>', false)
            ->assertDontSee('Obrisan oglas')
            ->assertDontSee('Oglas na čekanju')
            ->assertSee('noindex', false);
    }

    public function test_favorites_page_keyset_pages_and_empty_state(): void
    {
        config(['messages.favorites_per_page' => 2]);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/moj-nalog/omiljeni/')->assertSee('Još niste sačuvali nijedan oglas.');

        $listings = Listing::factory()->count(3)->create();
        foreach ($listings as $i => $listing) {
            DB::table('favorites')->insert(['user_id' => $user->id, 'listing_id' => $listing->id, 'created_at' => now()->subMinutes(10 - $i)]);
        }

        $html = $this->actingAs($user)->get('/moj-nalog/omiljeni/')->assertSee('Starije sačuvano')->getContent();
        $this->assertStringContainsString($listings[2]->title, $html);
        $this->assertStringNotContainsString($listings[0]->title, $html);
        preg_match('/\?cursor=([^"]+)"/', $html, $m);

        $this->actingAs($user)->get('/moj-nalog/omiljeni/?cursor='.$m[1])
            ->assertSee($listings[0]->title)
            ->assertDontSee($listings[2]->title)
            ->assertDontSee('Starije sačuvano');
    }
}
