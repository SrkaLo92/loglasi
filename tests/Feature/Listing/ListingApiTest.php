<?php

namespace Tests\Feature\Listing;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListingApiTest extends TestCase
{
    private const CARD_KEYS = ['id', 'title', 'price', 'currency', 'price_label', 'location', 'category', 'image', 'featured', 'seller_type', 'status', 'published_at', 'url'];

    private const DETAIL_KEYS = ['description', 'images', 'fields', 'condition', 'seller', 'views', 'is_favorite', 'is_mine', 'expires_at', 'reject_reason', 'lat', 'lng'];

    public function test_detail_matches_contract(): void
    {
        $listing = Listing::factory()->for(User::factory()->firm())->create(['price' => 8900]);

        $response = $this->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->assertHeader('ETag')
            ->assertJsonStructure(array_merge(self::CARD_KEYS, self::DETAIL_KEYS, ['seller' => ['id', 'name', 'type', 'firm_id', 'logo', 'phone_verified', 'member_since', 'active_listings']]));

        $response->assertJsonPath('id', $listing->id)
            ->assertJsonPath('price', 8900)
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('seller.type', 'firm')
            ->assertJsonPath('is_mine', false)
            ->assertJsonPath('url', url_slash('oglas/'.$listing->slug));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertArrayNotHasKey('phone', $response->json('seller'));
    }

    public function test_detail_counts_view_once_and_supports_304(): void
    {
        $listing = Listing::factory()->create(['views' => 0]);

        $etag = $this->getJson('/api/v1/listings/'.$listing->id)->headers->get('ETag');
        $this->getJson('/api/v1/listings/'.$listing->id, ['If-None-Match' => $etag])->assertStatus(304);

        $this->assertSame(1, (int) $listing->fresh()->views);
    }

    public function test_detail_for_owner_is_private_and_shows_pending(): void
    {
        $listing = Listing::factory()->create(['status' => ListingStatus::Rejected, 'rejection_reason' => 'Nejasne slike']);

        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound()->assertJsonPath('code', 'not_found');

        Sanctum::actingAs($listing->user);
        $response = $this->getJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('status', 'rejected')
            ->assertJsonPath('is_mine', true)
            ->assertJsonPath('reject_reason', 'Nejasne slike');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_unknown_and_deleted_listing_are_404(): void
    {
        $this->getJson('/api/v1/listings/999999')->assertNotFound();

        $listing = Listing::factory()->create();
        $listing->delete();
        $this->getJson('/api/v1/listings/'.$listing->id)->assertNotFound();
    }

    public function test_similar_returns_up_to_ten_cards_same_category_first(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);
        $sibling = Category::factory()->create(['parent_id' => $parent->id]);
        $location = Location::factory()->create();
        $listing = Listing::factory()->create(['category_id' => $child->id, 'location_id' => $location->id]);
        $same = Listing::factory()->count(3)->create(['category_id' => $child->id, 'location_id' => $location->id]);
        Listing::factory()->count(9)->create(['category_id' => $sibling->id, 'location_id' => $location->id]);
        $featured = Listing::factory()->featured()->create(['category_id' => $child->id, 'location_id' => $location->id, 'bumped_at' => now()->subYear()]);

        $response = $this->getJson('/api/v1/listings/'.$listing->id.'/similar')->assertOk()->assertJsonCount(10);

        $ids = array_column($response->json(), 'id');
        $this->assertSame($featured->id, $ids[0]);
        $this->assertEqualsCanonicalizing($same->pluck('id')->all(), array_slice($ids, 1, 3));
        $this->assertNotContains($listing->id, $ids);
        $response->assertJsonStructure(['*' => self::CARD_KEYS]);
    }

    public function test_contact_requires_login_and_counts_once_a_day(): void
    {
        $listing = Listing::factory()->for(User::factory()->phoneVerified()->state(['phone' => '+381651234567']))->create(['phone_reveals' => 0]);

        $this->postJson('/api/v1/listings/'.$listing->id.'/contact')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/listings/'.$listing->id.'/contact')
            ->assertOk()
            ->assertExactJson(['phone' => '+381651234567', 'phone_verified' => true]);
        $this->postJson('/api/v1/listings/'.$listing->id.'/contact')->assertOk();

        $this->assertSame(1, (int) $listing->fresh()->phone_reveals);
    }

    public function test_firm_profile_matches_contract(): void
    {
        $user = User::factory()->firm()->create();
        Listing::factory()->for($user)->count(2)->create();

        $this->getJson('/api/v1/firms/'.$user->firm->id)
            ->assertOk()
            ->assertJsonStructure(['id', 'name', 'about', 'logo', 'address', 'website', 'working_hours', 'phone_verified', 'package', 'package_expires_at', 'active_listings', 'member_since'])
            ->assertJsonPath('active_listings', 2)
            ->assertJsonPath('package', 'Business Max');
    }

    public function test_firm_of_blocked_or_unknown_account_is_404(): void
    {
        $user = User::factory()->firm()->create(['status' => 'blocked']);

        $this->getJson('/api/v1/firms/'.$user->firm->id)->assertNotFound();
        $this->getJson('/api/v1/firms/999999')->assertNotFound();
    }

    public function test_reports_api(): void
    {
        $listing = Listing::factory()->create();
        $this->postJson('/api/v1/reports', ['target_type' => 'listing', 'target_id' => $listing->id, 'reason' => 'prevara'])->assertUnauthorized();

        $reporter = User::factory()->create();
        Sanctum::actingAs($reporter);

        $this->postJson('/api/v1/reports', ['target_type' => 'listing', 'target_id' => $listing->id, 'reason' => 'prevara', 'note' => 'Traži avans'])->assertNoContent();
        $this->postJson('/api/v1/reports', ['target_type' => 'listing', 'target_id' => $listing->id, 'reason' => 'prevara'])->assertNoContent();
        $this->assertDatabaseCount('reports', 1);
        $this->assertDatabaseHas('reports', ['reporter_id' => $reporter->id, 'reportable_id' => $listing->id, 'details' => 'Traži avans']);

        $this->postJson('/api/v1/reports', ['target_type' => 'user', 'target_id' => $listing->user_id, 'reason' => 'drugo'])->assertNoContent();
    }

    public function test_reports_api_rejects_invalid_self_and_missing_targets(): void
    {
        $reporter = User::factory()->create();
        Sanctum::actingAs($reporter);

        $this->postJson('/api/v1/reports', ['target_type' => 'oglas', 'target_id' => 1, 'reason' => 'x'])->assertStatus(422)->assertJsonPath('code', 'validation_failed');
        $this->postJson('/api/v1/reports', ['target_type' => 'user', 'target_id' => $reporter->id, 'reason' => 'drugo'])->assertStatus(422);
        $this->postJson('/api/v1/reports', ['target_type' => 'listing', 'target_id' => 999999, 'reason' => 'drugo'])->assertNotFound();
    }

    public function test_reports_api_is_rate_limited(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $listing = Listing::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/reports', ['target_type' => 'listing', 'target_id' => $listing->id, 'reason' => 'drugo']);
        }
        $this->postJson('/api/v1/reports', ['target_type' => 'listing', 'target_id' => $listing->id, 'reason' => 'drugo'])
            ->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }
}
