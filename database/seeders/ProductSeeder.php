<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/** Cene dogovorene 23. 10. 2026; posle se menjaju iz admina. */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['code' => Product::BUMP, 'name' => 'Podizanje oglasa', 'price_rsd' => 50, 'duration_days' => null, 'sort' => 1],
            ['code' => Product::FEATURED_7, 'name' => 'Istaknut oglas 7 dana', 'price_rsd' => 300, 'duration_days' => 7, 'sort' => 2],
            ['code' => Product::FEATURED_30, 'name' => 'Istaknut oglas 30 dana', 'price_rsd' => 800, 'duration_days' => 30, 'sort' => 3],
            ['code' => Product::BIZNIS_12M, 'name' => 'Biznis paket 12 meseci', 'price_rsd' => 6000, 'duration_days' => 365, 'sort' => 4],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(['code' => $product['code']], $product);
        }
    }
}
