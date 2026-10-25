<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Price list of 25 Oct 2026 (on top of 24 Oct); prices are changed in the admin later.
 * Products from data/price-list-2026-10-24.php and -25.php that payments sells are active, the rest display-only.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['code' => Product::BUMP, 'name' => 'Podizanje oglasa', 'price_rsd' => 49, 'duration_days' => null, 'sort' => 1],
            ['code' => Product::FEATURED_7, 'name' => 'Istaknut oglas 7 dana', 'price_rsd' => 199, 'duration_days' => 7, 'sort' => 2],
            ['code' => Product::FEATURED_30, 'name' => 'Istaknut oglas 30 dana', 'price_rsd' => 800, 'duration_days' => 30, 'sort' => 3, 'is_active' => false], // retired 25 Oct 2026
            ['code' => Product::BIZNIS_12M, 'name' => 'Biznis paket 12 meseci', 'price_rsd' => 6000, 'duration_days' => 365, 'sort' => 4, 'is_active' => false], // retired, replaced by the Business packages
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['code' => $product['code']], $product);
        }

        // Products the payments module sells (config('payments.catalog')) are on sale, the rest display-only.
        foreach ([...require __DIR__.'/data/price-list-2026-10-24.php', ...require __DIR__.'/data/price-list-2026-10-25.php'] as $product) {
            $sellable = config('payments.catalog.'.$product['code']) !== null;
            Product::updateOrCreate(['code' => $product['code']], $product + ['is_active' => $sellable, 'display_only' => ! $sellable]);
        }

        // Price list of 25 Oct 2026: +30 oglasa retired, Business Start 50 listings, Business Pro unlimited.
        Product::query()->where('code', 'listing_slots_30')->update(['is_active' => false, 'display_only' => false]);
        Product::query()->where('code', 'like', 'business_start_%')->update(['listing_limit' => 50]);
        Product::query()->where('code', 'like', 'business_pro_%')->update(['listing_limit' => null]);
    }
}
