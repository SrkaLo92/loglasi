<?php

namespace Tests\Feature\Listing;

use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use Tests\TestCase;

class ProfilePageTest extends TestCase
{
    private function firm(array $firm = []): User
    {
        $user = User::factory()->firm()->phoneVerified()->create(['phone' => '+381641112223']);
        $user->firm->forceFill($firm + ['activity' => 'Auto servis', 'working_hours' => ['1' => '08-16']])->save();

        return $user->fresh();
    }

    public function test_firm_profile_shows_header_contact_hours_and_listings(): void
    {
        $user = $this->firm(['address' => 'Kneza Miloša 1', 'website' => 'https://www.primer.rs/', 'verified_at' => now()]);
        $listing = Listing::factory()->for($user)->create(['title' => 'Oglas firme jedan']);

        $this->get('/korisnik/'.$user->slug.'/')
            ->assertOk()
            ->assertSee('<h1 class="lgp-firm__name"', false)
            ->assertSee($user->firm->name)
            ->assertSee('Proverena firma')
            ->assertSee('Telefon proveren')
            ->assertSee('Auto servis')
            ->assertSee('1 aktivan oglas')
            ->assertSee('tel:+381641112223', false)
            ->assertSee('O firmi i kontakt')
            ->assertSee('Radno vreme')
            ->assertSee('primer.rs')
            ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=', false)
            ->assertSee('/moj-nalog/poruke/novo/?oglas='.$listing->id, false)
            ->assertSee($listing->title)
            ->assertDontSee('noindex');
    }

    public function test_firm_profile_has_green_call_button_and_sticky_bar(): void
    {
        $user = $this->firm();

        $this->get('/korisnik/'.$user->slug.'/')
            ->assertOk()
            ->assertSee('<a class="lgp-btn lgp-btn--call" href="tel:+381641112223">', false)
            ->assertSee('<span>Pozovi</span>', false)
            ->assertSee('<div class="lgp-sticky" role="region" aria-label="Brzi kontakt">', false)
            ->assertSee('lgp-firm-page has-sticky', false);
    }

    public function test_firm_profile_without_phone_has_no_call_button(): void
    {
        $user = $this->firm(['phone' => null]);
        $user->forceFill(['phone' => null])->save();

        $this->get('/korisnik/'.$user->slug.'/')
            ->assertOk()
            ->assertDontSee('lgp-btn--call', false)
            ->assertDontSee('Pozovi firmu')
            ->assertDontSee('lgp-sticky', false);
    }

    public function test_empty_firm_profile_is_noindex(): void
    {
        // Indexed only with an active listing or a filled profile (SEO plan, section 5).
        $user = $this->firm(['package' => null, 'package_ends_at' => null, 'description' => null, 'logo_path' => null]);

        $this->get('/korisnik/'.$user->slug.'/')->assertOk()->assertSee('noindex', false);
    }

    public function test_private_profile_is_simple_and_noindex(): void
    {
        $user = User::factory()->create(['name' => 'Marko Marković']);
        Listing::factory()->for($user)->count(2)->create();
        Listing::factory()->for($user)->pending()->create(['title' => 'Na čekanju']);

        $this->get('/korisnik/'.$user->slug.'/')
            ->assertOk()
            ->assertSee('Privatno lice')
            ->assertSee('2 oglasa')
            ->assertSee('noindex', false)
            ->assertDontSee('Na čekanju')
            ->assertDontSee('lgp-firm');
    }

    public function test_profile_views_counted_once_and_not_for_owner(): void
    {
        $user = $this->firm();

        $this->get('/korisnik/'.$user->slug.'/');
        $this->get('/korisnik/'.$user->slug.'/');
        $this->assertSame(1, (int) $user->firm->fresh()->profile_views);

        $this->actingAs($user)->get('/korisnik/'.$user->slug.'/');
        $this->assertSame(1, (int) $user->firm->fresh()->profile_views);
    }

    public function test_owner_sees_hints_and_stats_visitors_do_not(): void
    {
        $user = $this->firm(['description' => null, 'address' => null]);

        $this->get('/korisnik/'.$user->slug.'/')->assertDontSee('Dopunite profil')->assertDontSee('Statistika (30 dana)');
        $this->actingAs($user)->get('/korisnik/'.$user->slug.'/')
            ->assertSee('Dopunite profil')
            ->assertSee('Statistika (30 dana)');
    }

    public function test_category_tabs_filter_listings(): void
    {
        $user = User::factory()->create();
        $cars = Category::factory()->create(['name' => 'Automobili', 'slug' => 'automobili']);
        $flats = Category::factory()->create(['name' => 'Stanovi', 'slug' => 'stanovi']);
        Listing::factory()->for($user)->create(['category_id' => $cars->id, 'title' => 'Auto oglas']);
        Listing::factory()->for($user)->create(['category_id' => $flats->id, 'title' => 'Stan oglas']);

        $this->get('/korisnik/'.$user->slug.'/')->assertSee('Svi')->assertSee('?kategorija=automobili', false)->assertSee('Auto oglas')->assertSee('Stan oglas');
        $this->get('/korisnik/'.$user->slug.'/?kategorija=automobili')->assertOk()->assertSee('Auto oglas')->assertDontSee('Stan oglas');
        $this->get('/korisnik/'.$user->slug.'/?kategorija=nema')->assertNotFound();
    }

    public function test_pagination_and_page_out_of_range(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $location = Location::factory()->create();
        Listing::factory()->for($user)->count(25)->create(['category_id' => $category->id, 'location_id' => $location->id]);

        $this->get('/korisnik/'.$user->slug.'/')->assertOk()->assertSee('?page=2', false);
        $this->get('/korisnik/'.$user->slug.'/?page=2')->assertOk()->assertSee('aria-current="page"', false);
        $this->get('/korisnik/'.$user->slug.'/?page=3')->assertNotFound();
    }

    public function test_blocked_user_is_404_unknown_404_deleted_410(): void
    {
        $blocked = User::factory()->create(['status' => UserStatus::Blocked]);
        $this->get('/korisnik/'.$blocked->slug.'/')->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get('/korisnik/'.$blocked->slug.'/')->assertOk();
        auth()->logout();

        $this->get('/korisnik/nema-ga/')->assertNotFound();

        $deleted = User::factory()->create();
        $deleted->delete();
        $this->get('/korisnik/'.$deleted->slug.'/')->assertStatus(410);
    }
}
