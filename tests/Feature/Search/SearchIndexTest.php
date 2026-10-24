<?php

namespace Tests\Feature\Search;

use App\Models\Category;
use App\Models\Listing;
use App\Models\SearchEntry;
use App\Services\Catalog;
use App\Services\ListingSearch;
use App\Services\SearchIndex;
use App\Support\Search\SearchText;
use Illuminate\Support\Facades\DB;

class SearchIndexTest extends SearchTestCase
{
    public function test_model_changes_keep_the_index_in_sync(): void
    {
        $listing = $this->listing(['title' => 'Golf']);
        $this->assertTrue(SearchEntry::whereKey($listing->id)->exists());
        $this->assertSame([$this->vehicles->id, $this->cars->id, null], [SearchEntry::find($listing->id)->cat1, SearchEntry::find($listing->id)->cat2, SearchEntry::find($listing->id)->cat3]);

        $listing->update(['status' => 'pending']);
        $this->assertFalse(SearchEntry::whereKey($listing->id)->exists());

        $listing->update(['status' => 'published']);
        $listing->delete();
        $this->assertFalse(SearchEntry::whereKey($listing->id)->exists());

        $listing->restore();
        $this->assertTrue(SearchEntry::whereKey($listing->id)->exists());
    }

    public function test_moving_a_category_updates_paths(): void
    {
        $listing = $this->listing();
        $this->cars->update(['parent_id' => $this->homes->id]);

        $this->assertSame($this->homes->id, SearchEntry::find($listing->id)->cat1);
        $this->assertSame([$listing->title], array_column($this->getJson('/api/v1/listings?category='.$this->homes->id)->json('items'), 'title'));
    }

    public function test_refresh_removes_expired_and_ends_featured(): void
    {
        $expiring = $this->listing(['title' => 'Ističe']);
        $featured = $this->listing(['title' => 'Istaknut', 'featured_until' => now()->addHour()]);
        DB::table('listings')->where('id', $expiring->id)->update(['expires_at' => now()->subMinute()]);
        DB::table('listing_search')->where('id', $expiring->id)->update(['expires_at' => now()->subMinute()]);
        DB::table('listing_search')->where('id', $featured->id)->update(['featured_until' => now()->subMinute()]);

        $this->assertSame(2, app(SearchIndex::class)->refresh());
        $this->assertFalse(SearchEntry::whereKey($expiring->id)->exists());
        $this->assertFalse((bool) SearchEntry::find($featured->id)->featured);
        $this->artisan('search:refresh')->assertSuccessful();
    }

    public function test_rebuild_after_a_bulk_import(): void
    {
        $listing = $this->listing(['title' => 'Uvezen']);
        DB::table('listing_search')->delete();
        DB::table('listings')->where('id', $listing->id)->update(['title' => 'Uvezen ponovo']);

        $this->artisan('search:rebuild')->assertSuccessful();

        $this->assertSame(1, SearchEntry::count());
        $this->assertStringContainsString('ponovo', SearchEntry::find($listing->id)->search_text);
    }

    public function test_deep_pages_stop_at_the_offset_limit(): void
    {
        $this->listing();
        $search = app(ListingSearch::class);
        $page = (int) (ListingSearch::MAX_OFFSET / 50) + 2;

        $result = $search->paginate($search->validate(['per_page' => 50, 'page' => $page]));

        $this->assertCount(0, $result->items());
        $this->assertSame(1, $result->total());
    }

    public function test_counts_are_cached_until_a_listing_changes(): void
    {
        $search = app(ListingSearch::class);
        $this->listing();
        $this->assertSame(1, $search->count([]));

        DB::table('listing_search')->delete(); // behind the cache
        $this->assertSame(1, $search->count([]));

        $this->listing();
        $this->assertSame(1, $search->count([]));
    }

    public function test_search_text_normalization(): void
    {
        $this->assertSame('cevapi u lesnici djak dzem', SearchText::normalize('Ćevapi u Lešnici, Đak & Džem!'));
        $this->assertSame('+golf* +a4qqq', SearchText::booleanQuery('Golf A4'));
        $this->assertStringContainsString('a4qqq', SearchText::forListing('Audi A4', ''));
        $this->assertCount(10, SearchText::words(implode(' ', range(100, 130))));
    }

    public function test_invisible_category_returns_nothing(): void
    {
        $this->listing();
        Category::whereKey($this->vehicles->id)->update(['is_active' => false]);
        app(Catalog::class)->flush();

        $this->assertSame([], array_column($this->getJson('/api/v1/listings?category='.$this->cars->id)->json('items'), 'title'));
        $this->assertInstanceOf(Listing::class, Listing::first());
    }
}
