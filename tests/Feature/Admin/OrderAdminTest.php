<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Models\AuditLog;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderFulfillment;
use Database\Seeders\ProductSeeder;
use DomainException;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use ActsAsStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    private function order(string $code, ?Listing $listing = null, ?User $user = null): Order
    {
        $product = Product::byCode($code);
        $user ??= $listing?->user ?? User::factory()->create();

        return Order::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'listing_id' => $listing?->id,
            'amount_rsd' => $product->price_rsd,
            'reference' => 'LG-'.fake()->unique()->numerify('######'),
        ]);
    }

    public function test_mark_paid_featured_7_from_panel(): void
    {
        $listing = Listing::factory()->create(['featured_until' => null]);
        $order = $this->order(Product::FEATURED_7, $listing);
        $this->signIn($admin = $this->admin());

        Livewire::test(ListOrders::class)->callTableAction('markPaid', $order, ['note' => 'Uplata 24. 10.'])->assertHasNoTableActionErrors();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame($admin->id, $order->marked_paid_by);
        $this->assertSame('Uplata 24. 10.', $order->note);
        $this->assertTrue($listing->refresh()->featured_until->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));
        $log = AuditLog::where('action', 'order.mark_paid')->first();
        $this->assertSame($order->reference, $log->changes['reference']);
        $this->assertSame($admin->id, $log->user_id);
    }

    public function test_featured_30_extends_a_running_feature(): void
    {
        $listing = Listing::factory()->create(['featured_until' => now()->addDays(5)]);
        $order = $this->order(Product::FEATURED_30, $listing);

        app(OrderFulfillment::class)->markPaid($order, $this->admin());

        $this->assertTrue($listing->refresh()->featured_until->between(now()->addDays(35)->subMinute(), now()->addDays(35)->addMinute()));
    }

    public function test_bump_sets_bumped_at_now(): void
    {
        $listing = Listing::factory()->create(['bumped_at' => now()->subMonth()]);
        app(OrderFulfillment::class)->markPaid($this->order(Product::BUMP, $listing), $this->admin());

        $this->assertTrue($listing->refresh()->bumped_at->isAfter(now()->subMinute()));
    }

    public function test_biznis_adds_365_days_and_makes_a_firm(): void
    {
        $private = User::factory()->create();
        app(OrderFulfillment::class)->markPaid($this->order(Product::BIZNIS_12M, user: $private), $this->admin());

        $private->refresh();
        $this->assertSame(AccountType::Firm, $private->account_type);
        $this->assertTrue($private->firm->biznis_until->between(now()->addDays(365)->subMinute(), now()->addDays(365)->addMinute()));

        $firmUser = User::factory()->create(['account_type' => AccountType::Firm]);
        $firm = Firm::factory()->for($firmUser)->create();
        $firm->forceFill(['biznis_until' => now()->addDays(10)])->save();
        app(OrderFulfillment::class)->markPaid($this->order(Product::BIZNIS_12M, user: $firmUser), $this->admin());
        $this->assertTrue($firm->refresh()->biznis_until->between(now()->addDays(375)->subMinute(), now()->addDays(375)->addMinute()));
    }

    public function test_mark_paid_is_idempotent(): void
    {
        $listing = Listing::factory()->create(['featured_until' => null]);
        $order = $this->order(Product::FEATURED_7, $listing);
        $admin = $this->admin();

        app(OrderFulfillment::class)->markPaid($order, $admin);
        $first = $listing->refresh()->featured_until;
        app(OrderFulfillment::class)->markPaid($order->fresh(), $admin);

        $this->assertTrue($listing->refresh()->featured_until->equalTo($first));
    }

    public function test_cancel_and_cancelled_cannot_be_paid(): void
    {
        $listing = Listing::factory()->create(['featured_until' => null]);
        $order = $this->order(Product::FEATURED_7, $listing);
        $this->signIn($this->admin());

        Livewire::test(ListOrders::class)->callTableAction('cancel', $order, ['note' => 'Nije uplaćeno.']);
        $this->assertSame(OrderStatus::Cancelled, $order->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'order.cancel', 'subject_id' => $order->id]);

        Livewire::withQueryParams(['tab' => 'all'])->test(ListOrders::class)->assertTableActionHidden('markPaid', $order);

        $this->expectException(DomainException::class);
        app(OrderFulfillment::class)->markPaid($order, $this->admin());
    }

    public function test_listing_product_without_listing_is_rejected_and_rolled_back(): void
    {
        $order = $this->order(Product::FEATURED_7);

        try {
            app(OrderFulfillment::class)->markPaid($order, $this->admin());
            $this->fail('Expected DomainException');
        } catch (DomainException) {
        }

        $this->assertSame(OrderStatus::Pending, $order->refresh()->status);
    }

    public function test_moderator_can_mark_paid(): void
    {
        $listing = Listing::factory()->create();
        $order = $this->order(Product::BUMP, $listing);
        $this->signIn($this->moderator());

        Livewire::test(ListOrders::class)->callTableAction('markPaid', $order);
        $this->assertSame(OrderStatus::Paid, $order->refresh()->status);
    }
}
