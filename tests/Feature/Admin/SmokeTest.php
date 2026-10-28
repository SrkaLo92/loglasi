<?php

namespace Tests\Feature\Admin;

use App\Models\Field;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ProductSeeder;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use ActsAsStaff;

    public function test_every_page_renders_for_admin(): void
    {
        $this->seed(ProductSeeder::class);
        $admin = $this->admin();
        $listing = Listing::factory()->pending()->create();
        $firmUser = User::factory()->firm()->create();
        Order::create(['user_id' => $listing->user_id, 'product_id' => Product::byCode('bump')->id, 'listing_id' => $listing->id, 'amount_rsd' => 50, 'reference' => 'LG-1']);
        $field = Field::create(['key' => 'marka', 'label' => 'Marka', 'type' => 'select', 'options' => [['value' => 'bmw', 'label' => 'BMW']]]);
        $listing->category->fields()->attach($field->id, ['required' => false, 'sort' => 0]);

        $this->signIn($admin);

        foreach ([
            '/admin', '/admin/listings', '/admin/listings/'.$listing->id.'/edit', '/admin/users', '/admin/users/'.$listing->user_id,
            '/admin/users/'.$listing->user_id.'/edit', '/admin/firms', '/admin/firms/'.$firmUser->firm->id.'/edit', '/admin/orders',
            '/admin/reports', '/admin/settings', '/admin/products', '/admin/setting-keys', '/admin/categories',
            '/admin/categories/'.$listing->category_id.'/edit', '/admin/categories/create', '/admin/fields', '/admin/fields/'.$field->id.'/edit',
            '/admin/uvoz-oglasa', '/admin/locations', '/admin/redirects', '/admin/gone-urls', '/admin/audit-logs', '/admin/profile', '/admin/notifications', '/admin/system',
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
