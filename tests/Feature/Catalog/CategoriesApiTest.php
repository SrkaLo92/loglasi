<?php

namespace Tests\Feature\Catalog;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Services\Catalog;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CategoriesApiTest extends TestCase
{
    private Category $vozila;

    private Category $automobili;

    private Category $motocikli;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vozila = Category::create(['name' => 'Vozila', 'slug' => 'vozila', 'sort' => 1]);
        $this->automobili = Category::create(['name' => 'Automobili', 'slug' => 'automobili', 'parent_id' => $this->vozila->id, 'sort' => 1]);
        $this->motocikli = Category::create(['name' => 'Motocikli', 'slug' => 'motocikli', 'parent_id' => $this->vozila->id, 'sort' => 2]);
        Category::create(['name' => 'Građevinarstvo', 'slug' => 'gradjevinarstvo', 'sort' => 2, 'icon_path' => 'ikone/gradjevina.svg']);
    }

    public function test_returns_tree_with_children_counts_and_icons(): void
    {
        $response = $this->getJson('/api/v1/categories')->assertOk();

        $response->assertExactJson([
            [
                'id' => $this->vozila->id, 'name' => 'Vozila', 'slug' => 'vozila', 'icon' => null, 'count' => 0,
                'children' => [
                    ['id' => $this->automobili->id, 'name' => 'Automobili', 'slug' => 'automobili', 'icon' => null, 'count' => 0, 'children' => []],
                    ['id' => $this->motocikli->id, 'name' => 'Motocikli', 'slug' => 'motocikli', 'icon' => null, 'count' => 0, 'children' => []],
                ],
            ],
            [
                'id' => Category::where('slug', 'gradjevinarstvo')->value('id'), 'name' => 'Građevinarstvo', 'slug' => 'gradjevinarstvo',
                'icon' => url('/storage/ikone/gradjevina.svg'), 'count' => 0, 'children' => [],
            ],
        ]);
    }

    public function test_count_includes_subcategories_once_and_only_published(): void
    {
        Listing::factory()->count(2)->create(['category_id' => $this->automobili->id]);
        Listing::factory()->create(['category_id' => $this->motocikli->id]);
        Listing::factory()->create(['category_id' => $this->vozila->id]);
        Listing::factory()->pending()->create(['category_id' => $this->automobili->id]);
        Listing::factory()->expired()->create(['category_id' => $this->automobili->id]);
        Listing::factory()->create(['category_id' => $this->automobili->id, 'status' => ListingStatus::Rejected]);
        Listing::factory()->create(['category_id' => $this->automobili->id])->delete();

        $tree = $this->getJson('/api/v1/categories')->assertOk()->json();

        $this->assertSame(4, $tree[0]['count']);
        $this->assertSame(2, $tree[0]['children'][0]['count']);
        $this->assertSame(1, $tree[0]['children'][1]['count']);
        $this->assertSame(0, $tree[1]['count']);
    }

    public function test_inactive_categories_are_hidden_with_their_children(): void
    {
        $this->vozila->update(['is_active' => false]);

        $tree = $this->getJson('/api/v1/categories')->assertOk()->json();

        $this->assertSame(['gradjevinarstvo'], array_column($tree, 'slug'));
    }

    public function test_cache_headers_unicode_and_etag_304(): void
    {
        $response = $this->get('/api/v1/categories')->assertOk();

        $this->assertStringContainsString('Građevinarstvo', $response->getContent());
        $this->assertStringContainsString('max-age=300', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $etag = $response->headers->get('ETag');
        $this->assertMatchesRegularExpression('~^W/"[a-f0-9]{32}"$~', $etag);

        $notModified = $this->get('/api/v1/categories', ['If-None-Match' => $etag]);
        $notModified->assertStatus(304);
        $this->assertSame('', $notModified->getContent());
        $this->assertSame($etag, $notModified->headers->get('ETag'));

        $this->get('/api/v1/categories', ['If-None-Match' => 'W/"drugi", '.trim($etag, 'W/')])->assertStatus(304);
        $this->get('/api/v1/categories', ['If-None-Match' => 'W/"drugi"'])->assertOk();
    }

    public function test_new_listing_invalidates_counts_and_etag(): void
    {
        $etag = $this->get('/api/v1/categories')->headers->get('ETag');

        Listing::factory()->create(['category_id' => $this->automobili->id]);

        $response = $this->get('/api/v1/categories', ['If-None-Match' => $etag])->assertOk();
        $this->assertSame(1, $response->json('0.count'));
    }

    public function test_status_change_and_delete_invalidate_counts(): void
    {
        $listing = Listing::factory()->pending()->create(['category_id' => $this->automobili->id]);
        $this->assertSame(0, $this->getJson('/api/v1/categories')->json('0.count'));

        $listing->update(['status' => ListingStatus::Published]);
        $this->assertSame(1, $this->getJson('/api/v1/categories')->json('0.count'));

        $listing->update(['category_id' => Category::where('slug', 'gradjevinarstvo')->value('id')]);
        $tree = $this->getJson('/api/v1/categories')->json();
        $this->assertSame([0, 1], [$tree[0]['count'], $tree[1]['count']]);

        $listing->delete();
        $this->assertSame(0, $this->getJson('/api/v1/categories')->json('1.count'));

        $listing->restore();
        $this->assertSame(1, $this->getJson('/api/v1/categories')->json('1.count'));
    }

    public function test_view_counter_does_not_flush_counts(): void
    {
        $listing = Listing::factory()->create(['category_id' => $this->automobili->id]);
        app(Catalog::class)->counts();
        $this->assertTrue(Cache::has(Catalog::KEY_COUNTS));

        $listing->increment('views');
        $listing->update(['bumped_at' => now()]);

        $this->assertTrue(Cache::has(Catalog::KEY_COUNTS));
    }

    public function test_category_change_invalidates_tree(): void
    {
        $this->getJson('/api/v1/categories')->assertOk();

        $this->automobili->update(['name' => 'Putnička vozila']);

        $this->getJson('/api/v1/categories')->assertJsonPath('0.children.0.name', 'Putnička vozila');
    }

    public function test_head_request_and_rate_limit(): void
    {
        $this->head('/api/v1/categories')->assertOk();

        for ($i = 0; $i < 119; $i++) {
            $this->get('/api/v1/categories');
        }

        $this->getJson('/api/v1/categories')
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests')
            ->assertHeader('Retry-After');
    }
}
