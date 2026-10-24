<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Services\OrderFulfillment;
use App\Services\OrderService;

class OrdersApiTest extends PaymentsTestCase
{
    public function test_me_orders_returns_the_contract_shape(): void
    {
        $user = $this->seller();
        $listing = $this->listingOf($user);
        $pending = $this->order($user, 'featured_7', $listing);
        $paid = $this->pay($this->order($user, 'listing_slots_3'));
        $this->order($this->seller(), 'listing_slots_3'); // someone else's

        $token = $user->createToken('test')->plainTextToken;
        $items = $this->withToken($token)->getJson('/api/v1/me/orders')->assertOk()->json();

        $this->assertCount(2, $items);
        $byId = collect($items)->keyBy('id');
        $this->assertSame([
            'id' => $pending->id, 'product' => 'featured_7d', 'product_label' => 'Istaknut oglas 7 dana', 'amount' => 199, 'status' => 'awaiting_payment',
            'listing_id' => $listing->id, 'user' => ['id' => $user->id, 'name' => $user->name],
            'payment_reference' => $pending->reference,
            'created_at' => $pending->created_at->utc()->format('Y-m-d\TH:i:s\Z'), 'paid_at' => null,
        ], $byId[$pending->id]);
        $this->assertSame('paid', $byId[$paid->id]['status']);
        $this->assertSame('listing_slots_3', $byId[$paid->id]['product']);
        $this->assertSame('+3 oglasa', $byId[$paid->id]['product_label']);
    }

    public function test_new_products_and_every_status(): void
    {
        $user = $this->seller();
        $listing = $this->listingOf($user);
        $bump = $this->order($user, 'bump', $listing);
        $xl = $this->pay($this->order($user, 'premium_xl_home_7', $listing)); // activated: "paid"
        $refunded = $this->pay($this->order($user, 'listing_slots_10'));
        app(OrderFulfillment::class)->refund($refunded);
        $cancelled = $this->order($user, 'top_7', $listing);
        app(OrderService::class)->cancel($cancelled, $user);
        $firm = $this->firmUser();
        $package = $this->order($firm, 'business_pro_yearly', null, $this->firmBuyer());

        $items = collect($this->withToken($user->createToken('test')->plainTextToken)->getJson('/api/v1/me/orders')->assertOk()->json())->keyBy('id');

        $this->assertSame(OrderStatus::Activated, $xl->status);
        $this->assertSame(['bump', 'Podizanje oglasa', 'awaiting_payment'], [$items[$bump->id]['product'], $items[$bump->id]['product_label'], $items[$bump->id]['status']]);
        $this->assertSame(['premium_xl_home_7', 'Premium XL + početna', 'paid'], [$items[$xl->id]['product'], $items[$xl->id]['product_label'], $items[$xl->id]['status']]);
        $this->assertNotNull($items[$xl->id]['paid_at']);
        $this->assertSame(['listing_slots_10', 'refunded'], [$items[$refunded->id]['product'], $items[$refunded->id]['status']]);
        $this->assertSame(['top_7', 'cancelled'], [$items[$cancelled->id]['product'], $items[$cancelled->id]['status']]);

        // Contract keys (plus product_label) and types, newest first.
        $this->assertSame([$cancelled->id, $refunded->id, $xl->id, $bump->id], $items->keys()->all());
        foreach ($items as $item) {
            $this->assertSame(['id', 'product', 'product_label', 'amount', 'status', 'listing_id', 'user', 'payment_reference', 'created_at', 'paid_at'], array_keys($item));
            $this->assertIsString($item['product']);
            $this->assertIsInt($item['amount']);
            $this->assertContains($item['status'], ['awaiting_payment', 'paid', 'refunded', 'cancelled']);
            $this->assertMatchesRegularExpression('/^\d{8}$/', $item['payment_reference']);
        }

        $this->app['auth']->forgetGuards(); // next request as the firm
        $firmItem = $this->withToken($firm->createToken('test')->plainTextToken)->getJson('/api/v1/me/orders')->assertOk()->json('0');
        $this->assertSame(['id' => $package->id, 'product' => 'business_pro_yearly', 'product_label' => 'Business Pro, godišnje', 'listing_id' => null],
            ['id' => $firmItem['id'], 'product' => $firmItem['product'], 'product_label' => $firmItem['product_label'], 'listing_id' => $firmItem['listing_id']]);
    }

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/v1/me/orders')->assertUnauthorized();
    }
}
