<?php

namespace Tests\Feature\Messages;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiFavoritesTest extends TestCase
{
    public function test_put_get_delete(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $a = Listing::factory()->create(['title' => 'Prvi']);
        $b = Listing::factory()->rsd(12500)->create(['title' => 'Drugi']);

        $this->putJson('/api/v1/me/favorites/'.$a->id)->assertNoContent();
        $this->travel(1)->seconds();
        $this->putJson('/api/v1/me/favorites/'.$b->id)->assertNoContent();
        $this->putJson('/api/v1/me/favorites/'.$b->id)->assertNoContent(); // idempotent

        $this->assertSame(1, (int) DB::table('listing_daily_stats')->where('listing_id', $b->id)->value('favorites'));

        $this->getJson('/api/v1/me/favorites')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.id', $b->id)
            ->assertJsonPath('0.price', 12500)
            ->assertJsonPath('0.currency', 'RSD')
            ->assertJsonPath('0.price_label', '12.500 din')
            ->assertJsonPath('0.url', url_slash('oglas/'.$b->slug))
            ->assertJsonPath('1.id', $a->id);

        $this->deleteJson('/api/v1/me/favorites/'.$a->id)->assertNoContent();
        $this->deleteJson('/api/v1/me/favorites/'.$a->id)->assertNoContent();
        $this->deleteJson('/api/v1/me/favorites/999999')->assertNoContent();
        $this->getJson('/api/v1/me/favorites')->assertJsonCount(1);
    }

    public function test_put_unavailable_listing_is_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/me/favorites/999999')->assertStatus(404)->assertJsonPath('code', 'not_found');
        $this->putJson('/api/v1/me/favorites/'.Listing::factory()->pending()->create()->id)->assertStatus(404);
        $this->putJson('/api/v1/me/favorites/abc')->assertStatus(404);
        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_list_hides_deleted_and_marks_expired(): void
    {
        $user = User::factory()->create();
        $expired = Listing::factory()->create();
        $deleted = Listing::factory()->create();
        foreach ([$expired, $deleted] as $listing) {
            DB::table('favorites')->insert(['user_id' => $user->id, 'listing_id' => $listing->id, 'created_at' => now()]);
        }
        $expired->update(['expires_at' => now()->subHour()]);
        $deleted->delete();

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/me/favorites')->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.id', $expired->id)
            ->assertJsonPath('0.status', 'expired');
    }

    public function test_list_pages(): void
    {
        $user = User::factory()->create();
        $listings = Listing::factory()->count(3)->create();
        foreach ($listings as $i => $listing) {
            DB::table('favorites')->insert(['user_id' => $user->id, 'listing_id' => $listing->id, 'created_at' => now()->subMinutes(10 - $i)]);
        }
        Sanctum::actingAs($user);

        $first = $this->getJson('/api/v1/me/favorites?per_page=2')->assertJsonCount(2)->assertHeader('X-Next-Cursor');
        $this->getJson('/api/v1/me/favorites?per_page=2&cursor='.$first->headers->get('X-Next-Cursor'))
            ->assertJsonCount(1)->assertJsonPath('0.id', $listings[0]->id)->assertHeaderMissing('X-Next-Cursor');
        $this->getJson('/api/v1/me/favorites?per_page=2&page=2')->assertJsonCount(1)->assertJsonPath('0.id', $listings[0]->id);
        $this->getJson('/api/v1/me/favorites?per_page=99')->assertStatus(400)->assertJsonPath('code', 'invalid_param');
    }
}
