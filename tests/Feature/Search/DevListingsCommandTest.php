<?php

namespace Tests\Feature\Search;

use App\Models\Listing;
use App\Models\SearchEntry;

class DevListingsCommandTest extends SearchTestCase
{
    public function test_creates_listings_and_the_index(): void
    {
        $this->artisan('dev:listings', ['count' => 40])->assertSuccessful();

        $this->assertSame(40, Listing::count());
        $this->assertSame(Listing::query()->where('status', 'published')->where('expires_at', '>', now())->count(), SearchEntry::count());

        $this->artisan('dev:listings', ['count' => 5, '--fresh' => true])->assertSuccessful();
        $this->assertSame(5, Listing::count());
    }
}
