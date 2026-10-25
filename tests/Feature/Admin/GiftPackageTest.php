<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Payments\ListingSlots;
use App\Services\Payments\PlusSeller;
use App\Support\ListingLimits;
use Database\Seeders\ProductSeeder;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class GiftPackageTest extends TestCase
{
    use ActsAsStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public function test_admin_gives_plus_seller_and_extra_listings_for_free(): void
    {
        $user = User::factory()->create();
        $this->signIn($admin = $this->admin());

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->callAction('giftPackage', ['product' => 'plus_seller_90'])
            ->assertHasNoActionErrors();
        Livewire::test(ViewUser::class, ['record' => $user->getKey()])
            ->callAction('giftPackage', ['product' => 'listing_slots_10']);

        $this->assertTrue(PlusSeller::isActive($user));
        $this->assertSame(now()->addDays(90)->toDateString(), PlusSeller::activeUntil($user)->toDateString());
        $this->assertSame(10, ListingSlots::extra($user));
        $this->assertGreaterThanOrEqual(PlusSeller::listingLimit(), ListingLimits::for($user)['monthly_limit']);
        $this->assertDatabaseCount('orders', 0); // no order, invoice or receipt
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.gift_package', 'subject_id' => $user->id, 'user_id' => $admin->id]);
    }

    public function test_ending_gifts_keeps_paid_packages(): void
    {
        $user = User::factory()->create();
        PlusSeller::grant($user, 30, null);
        ListingSlots::grant($user, 3, now(), now()->addDays(30), null);
        $order = Order::query()->create([
            'user_id' => $user->id, 'product_id' => Product::query()->where('code', 'listing_slots_10')->value('id'),
            'amount_rsd' => 349, 'buyer_type' => 'private', 'reference' => 'T-1', 'status' => 'activated', 'is_test' => true,
        ]);
        ListingSlots::grant($user, 10, now(), now()->addDays(30), $order->id);
        $this->signIn($this->admin());

        Livewire::test(ViewUser::class, ['record' => $user->getKey()])->callAction('endGifts');

        $this->assertFalse(PlusSeller::isActive($user));
        $this->assertSame(10, ListingSlots::extra($user));
    }

    public function test_firms_get_packages_on_the_firm_page(): void
    {
        $firm = User::factory()->create(['account_type' => AccountType::Firm]);
        $this->signIn($this->admin());

        Livewire::test(ViewUser::class, ['record' => $firm->getKey()])->assertActionHidden('giftPackage');
    }
}
