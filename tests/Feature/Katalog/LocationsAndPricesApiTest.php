<?php

namespace Tests\Feature\Katalog;

use App\Models\Location;
use App\Models\Product;
use Database\Seeders\ProductSeeder;
use Tests\TestCase;

class LocationsAndPricesApiTest extends TestCase
{
    public function test_locations_sorted_by_sort_then_name(): void
    {
        Location::create(['name' => 'Tršić', 'slug' => 'trsic']);
        Location::create(['name' => 'Banja Koviljača', 'slug' => 'banja-koviljaca']);
        Location::create(['name' => 'Lešnica', 'slug' => 'lesnica']);

        $response = $this->get('/api/v1/locations')->assertOk();

        $this->assertSame(['Banja Koviljača', 'Lešnica', 'Tršić'], array_column($response->json(), 'name'));
        $response->assertJsonPath('0', ['id' => Location::where('slug', 'banja-koviljaca')->value('id'), 'name' => 'Banja Koviljača', 'slug' => 'banja-koviljaca']);
        $this->assertStringContainsString('Banja Koviljača', $response->getContent());
        $this->assertStringContainsString('max-age=300', $response->headers->get('Cache-Control'));

        $this->get('/api/v1/locations', ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);
    }

    public function test_new_location_invalidates_cache(): void
    {
        Location::create(['name' => 'Loznica', 'slug' => 'loznica']);
        $this->getJson('/api/v1/locations')->assertJsonCount(1);

        Location::create(['name' => 'Klupci', 'slug' => 'klupci']);
        $this->getJson('/api/v1/locations')->assertJsonCount(2)->assertJsonPath('0.name', 'Klupci');
    }

    public function test_prices_from_admin_products(): void
    {
        $this->seed(ProductSeeder::class);
        Product::create(['code' => 'start_paket', 'name' => 'Start', 'price_rsd' => 1000, 'sort' => 5]);

        $response = $this->getJson('/api/v1/prices')->assertOk();

        $response->assertExactJson([
            ['product' => 'bump', 'label' => 'Podizanje oglasa', 'amount' => 49],
            ['product' => 'featured_7d', 'label' => 'Istaknut oglas 7 dana', 'amount' => 199],
        ]);
        $this->assertStringContainsString('max-age=300', $response->headers->get('Cache-Control'));
        $this->get('/api/v1/prices', ['If-None-Match' => $response->headers->get('ETag')])->assertStatus(304);
    }

    public function test_price_change_and_deactivation_invalidate_cache(): void
    {
        $this->seed(ProductSeeder::class);
        $this->getJson('/api/v1/prices')->assertJsonCount(2); // retired: the 12-month package and Istaknut 30 dana

        Product::byCode(Product::BUMP)->update(['price_rsd' => 70]);
        Product::byCode(Product::FEATURED_7)->update(['is_active' => false]);

        $response = $this->getJson('/api/v1/prices')->assertJsonCount(1);
        $response->assertJsonPath('0.amount', 70);
        $this->assertNotContains('featured_7d', array_column($response->json(), 'product'));
    }
}
