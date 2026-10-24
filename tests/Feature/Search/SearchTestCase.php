<?php

namespace Tests\Feature\Search;

use App\Enums\Currency;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use App\Services\Catalog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Search tests run without a wrapping transaction: InnoDB FULLTEXT does not see rows
 * that are not committed. Tables are emptied before and after every test instead.
 */
abstract class SearchTestCase extends TestCase
{
    protected $connectionsToTransact = [];

    protected Category $vehicles;

    protected Category $cars;

    protected Category $homes;

    protected Location $loznica;

    protected Location $banja;

    protected User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->truncateAll();

        $this->vehicles = Category::create(['name' => 'Vozila', 'slug' => 'vozila']);
        $this->cars = Category::create(['name' => 'Automobili', 'slug' => 'automobili', 'parent_id' => $this->vehicles->id]);
        $this->homes = Category::create(['name' => 'Nekretnine', 'slug' => 'nekretnine']);
        $this->loznica = Location::create(['name' => 'Loznica', 'slug' => 'loznica']);
        $this->banja = Location::create(['name' => 'Banja Koviljača', 'slug' => 'banja-koviljaca']);
        $this->seller = User::factory()->create();
        app(Catalog::class)->flush();
    }

    protected function tearDown(): void
    {
        $this->truncateAll();
        parent::tearDown();
    }

    private function truncateAll(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (array_map(fn ($row) => array_values((array) $row)[0], DB::select('SHOW TABLES')) as $table) {
            if ($table !== 'migrations') {
                DB::table($table)->truncate();
            }
        }
        Schema::enableForeignKeyConstraints();
        Cache::flush();
    }

    /** Published listing in "Automobili", Loznica, by the default seller. */
    protected function listing(array $attributes = []): Listing
    {
        return Listing::factory()->create($attributes + [
            'user_id' => $this->seller->id,
            'category_id' => $this->cars->id,
            'location_id' => $this->loznica->id,
            'description' => 'Opis oglasa.',
        ]);
    }

    protected function priced(string $title, ?int $price, Currency $currency = Currency::EUR, array $attributes = []): Listing
    {
        return $this->listing($attributes + ['title' => $title, 'price' => $price, 'currency' => $currency]);
    }

    /** @return list<string> titles of the API result */
    protected function apiTitles(string $query): array
    {
        return array_column($this->getJson('/api/v1/listings'.($query !== '' ? '?'.$query : ''))->assertOk()->json('items'), 'title');
    }
}
