<?php

namespace Tests\Feature\Payments;

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
            'id' => $pending->id, 'product' => 'featured_7d', 'amount' => 199, 'status' => 'awaiting_payment',
            'listing_id' => $listing->id, 'user' => ['id' => $user->id, 'name' => $user->name],
            'payment_reference' => $pending->reference,
            'created_at' => $pending->created_at->utc()->format('Y-m-d\TH:i:s\Z'), 'paid_at' => null,
        ], $byId[$pending->id]);
        $this->assertSame('paid', $byId[$paid->id]['status']);
        $this->assertSame('listing_slots_3', $byId[$paid->id]['product']);
    }

    public function test_requires_a_token(): void
    {
        $this->getJson('/api/v1/me/orders')->assertUnauthorized();
    }
}
