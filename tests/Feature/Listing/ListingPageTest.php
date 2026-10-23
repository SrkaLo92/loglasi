<?php

namespace Tests\Feature\Listing;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use Tests\TestCase;

class ListingPageTest extends TestCase
{
    private function url(Listing $listing): string
    {
        return '/oglas/'.$listing->slug.'/';
    }

    public function test_active_listing_renders_with_seller_and_masked_phone(): void
    {
        $seller = User::factory()->create(['name' => 'Petar Petrović', 'phone' => '+381641234567']);
        $listing = Listing::factory()->for($seller)->create([
            'title' => 'Golf 7 dizel',
            'price' => 8900,
            'description' => "Prvi red\n\nVidi www.primer.rs <b>bold</b>",
        ]);

        $response = $this->get($this->url($listing));

        $response->assertOk()
            ->assertSee('<h1 class="lgo-contact__title">Golf 7 dizel</h1>', false)
            ->assertSee('8.900 €')
            ->assertSee('Petar Petrović')
            ->assertSee('Prikaži broj')
            ->assertDontSee('641234567')
            ->assertSee('rel="nofollow ugc noopener"', false)
            ->assertSee('&lt;b&gt;bold&lt;/b&gt;', false)
            ->assertSee('/moj-nalog/poruke/novo/?oglas='.$listing->id, false)
            ->assertSee('Kreditni kalkulator (informativno)')
            ->assertDontSee('noindex');
    }

    public function test_firm_seller_shows_badge_and_profile_link(): void
    {
        $firm = User::factory()->firm()->create();
        $listing = Listing::factory()->for($firm)->create();

        $this->get($this->url($listing))
            ->assertOk()
            ->assertSee('Firma')
            ->assertSee('/korisnik/'.$firm->slug.'/', false);
    }

    public function test_gallery_uses_variants_and_eager_first_image(): void
    {
        $listing = Listing::factory()->create();
        ListingImage::create(['listing_id' => $listing->id, 'path' => 'listings/1/a.jpg', 'sort' => 0, 'width' => 1600, 'height' => 1200,
            'variants' => ['md' => ['webp' => 'listings/1/a-md.webp', 'jpg' => 'listings/1/a-md.jpg', 'width' => 800]]]);
        ListingImage::create(['listing_id' => $listing->id, 'path' => 'listings/1/b.jpg', 'sort' => 1, 'width' => 800, 'height' => 600]);

        $html = $this->get($this->url($listing))->assertOk()->getContent();

        $this->assertStringContainsString('a-md.webp', $html);
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('width="1600" height="1200"', $html);
    }

    public function test_expired_listing_is_200_with_notice_and_noindex(): void
    {
        $listing = Listing::factory()->expired()->create();

        $this->get($this->url($listing))
            ->assertOk()
            ->assertSee('noindex', false)
            ->assertSee('istekao');
    }

    public function test_pending_and_rejected_are_hidden_from_public_but_visible_to_owner_and_staff(): void
    {
        foreach ([ListingStatus::Pending, ListingStatus::Rejected] as $status) {
            $listing = Listing::factory()->create(['status' => $status]);

            $this->get($this->url($listing))->assertNotFound();
            $this->actingAs(User::factory()->create())->get($this->url($listing))->assertNotFound();
            $this->actingAs($listing->user)->get($this->url($listing))->assertOk();
            $this->actingAs(User::factory()->moderator()->create())->get($this->url($listing))->assertOk();
            auth()->logout();
        }
    }

    public function test_deleted_listing_is_gone(): void
    {
        $listing = Listing::factory()->create();
        $listing->delete();

        $this->get($this->url($listing))->assertStatus(410);
    }

    public function test_listing_of_blocked_seller_is_hidden(): void
    {
        $listing = Listing::factory()->for(User::factory()->state(['status' => UserStatus::Blocked]))->create();

        $this->get($this->url($listing))->assertNotFound();
    }

    public function test_unknown_slug_is_404(): void
    {
        $this->get('/oglas/ne-postoji/')->assertNotFound();
    }

    public function test_views_are_counted_once_per_session_with_daily_stats(): void
    {
        $listing = Listing::factory()->create(['views' => 0]);

        $this->get($this->url($listing))->assertOk();
        $this->get($this->url($listing))->assertOk();

        $this->assertSame(1, (int) $listing->fresh()->views);
        $this->assertDatabaseHas('listing_daily_stats', ['listing_id' => $listing->id, 'views' => 1]);
    }

    public function test_bots_and_owner_do_not_count_views(): void
    {
        $listing = Listing::factory()->create(['views' => 0]);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->get($this->url($listing))->assertOk();
        $this->withHeader('User-Agent', 'Mozilla/5.0')->actingAs($listing->user)->get($this->url($listing))->assertOk();

        $this->assertSame(0, (int) $listing->fresh()->views);
        $this->assertDatabaseCount('listing_daily_stats', 0);
    }

    public function test_breadcrumbs_and_similar_listings_from_same_category(): void
    {
        $parent = Category::factory()->create(['name' => 'Vozila', 'slug' => 'vozila']);
        $child = Category::factory()->create(['name' => 'Automobili', 'slug' => 'automobili', 'parent_id' => $parent->id]);
        $listing = Listing::factory()->create(['category_id' => $child->id]);
        $same = Listing::factory()->count(4)->create(['category_id' => $child->id]);
        $other = Listing::factory()->create(['title' => 'Sasvim drugi oglas']);

        $response = $this->get($this->url($listing))->assertOk();

        $response->assertSee('Vozila')->assertSee('Automobili')->assertSee('Slični oglasi');
        foreach ($same as $l) {
            $response->assertSee($l->title);
        }
        $response->assertDontSee('Sasvim drugi oglas');
    }

    public function test_owner_does_not_get_contact_bar(): void
    {
        $listing = Listing::factory()->create();

        $this->get($this->url($listing))->assertSee('data-lgo-bar', false);
        $this->actingAs($listing->user)->get($this->url($listing))->assertDontSee('data-lgo-bar', false);
    }
}
