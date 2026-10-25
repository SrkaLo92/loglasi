<?php

namespace Tests\Feature\Admin;

use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Models\Listing;
use App\Models\User;
use App\Services\OrderService;
use App\Services\Payments\BuyerDetails;
use Database\Seeders\ProductSeeder;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OpensPrivatePayments;
use Tests\TestCase;

/** /api/v1/admin/* for the admin tab of the mobile app. */
class AdminApiTest extends TestCase
{
    use OpensPrivatePayments;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_regular_user_and_guest_are_refused(): void
    {
        $this->getJson('/api/v1/admin/listings')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/listings')->assertForbidden();
    }

    public function test_moderator_works_the_queue_oldest_first(): void
    {
        $newer = Listing::factory()->pending()->create(['created_at' => now()->subHour()]);
        $older = Listing::factory()->pending()->create(['created_at' => now()->subDay()]);
        Sanctum::actingAs(User::factory()->moderator()->create());

        $this->getJson('/api/v1/admin/listings?status=pending')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('items.0.id', $older->id)
            ->assertJsonPath('items.1.id', $newer->id);

        $this->postJson('/api/v1/admin/listings/'.$older->id.'/approve')->assertOk()->assertJsonPath('id', $older->id);
        $this->assertSame(ListingStatus::Published, $older->refresh()->status);

        $this->postJson('/api/v1/admin/listings/'.$newer->id.'/reject', [])->assertStatus(422);
        $this->postJson('/api/v1/admin/listings/'.$newer->id.'/reject', ['reason' => 'Nejasne fotografije.'])->assertOk();
        $this->assertSame(ListingStatus::Rejected, $newer->refresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'listing.reject', 'subject_id' => $newer->id]);
    }

    public function test_moderator_cannot_touch_orders_or_phones(): void
    {
        $user = User::factory()->create(['phone' => '+381651234567']);
        Sanctum::actingAs(User::factory()->moderator()->create());

        $this->getJson('/api/v1/admin/orders')->assertForbidden();
        $this->postJson('/api/v1/admin/users/'.$user->id.'/verify-phone')->assertForbidden();
        $this->assertNull($user->refresh()->phone_verified_at);
    }

    public function test_admin_marks_an_order_paid(): void
    {
        $this->seed(ProductSeeder::class);
        $seller = User::factory()->create();
        $listing = Listing::factory()->for($seller)->create();
        $order = app(OrderService::class)->create($seller, 'featured_7', $listing, BuyerDetails::private('127.0.0.1'));
        Sanctum::actingAs($admin = User::factory()->admin()->create());

        $this->getJson('/api/v1/admin/orders')->assertOk()->assertJsonPath('0.payment_reference', $order->reference)->assertJsonPath('0.status', 'awaiting_payment');
        $this->postJson('/api/v1/admin/orders/'.$order->id.'/mark-paid', ['note' => 'Stiglo.'])->assertOk()->assertJsonPath('status', 'paid');

        $this->assertSame(OrderStatus::Activated, $order->refresh()->status);
        $this->assertSame($admin->id, $order->marked_paid_by);
        $this->postJson('/api/v1/admin/orders/'.$order->id.'/mark-paid')->assertStatus(409);
    }

    public function test_admin_verifies_a_phone_and_reads_stats(): void
    {
        $user = User::factory()->create(['phone' => '+381651234567']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/v1/admin/users?filter=phone_unverified')->assertOk()->assertJsonPath('0.id', $user->id);
        $this->postJson('/api/v1/admin/users/'.$user->id.'/verify-phone')->assertOk()->assertJsonPath('phone_verified', true);

        $this->getJson('/api/v1/admin/stats?period=7d')->assertOk()->assertJsonStructure([
            'pending_listings', 'new_listings', 'new_users', 'orders_awaiting_payment', 'revenue_paid', 'listings_daily', 'by_category',
        ]);
        $this->getJson('/api/v1/admin/stats?period=1y')->assertStatus(400);
    }
}
