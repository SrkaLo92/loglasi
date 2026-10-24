<?php

namespace Tests\Feature\Payments;

use App\Enums\AccountType;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderFulfillment;
use App\Services\OrderService;
use App\Services\Payments\BuyerDetails;
use App\Support\Settings;
use Database\Seeders\ProductSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Shared setup: the price list, payee settings (synthetic account), helpers to order and pay. */
abstract class PaymentsTestCase extends TestCase
{
    public const ACCOUNT = '160-0000000123456-54';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
        Settings::set('payment_recipient', "PRIMALAC PR TEST\nKlupci, Loznica");
        Settings::set('payment_account', self::ACCOUNT);
        Mail::fake();
    }

    protected function seller(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    protected function listingOf(User $user, array $attributes = []): Listing
    {
        return Listing::factory()->for($user)->create($attributes + ['bumped_at' => now()->subDays(2)]);
    }

    protected function firmUser(?string $package = null, bool $verified = true): User
    {
        $user = User::factory()->create(['account_type' => AccountType::Firm]);
        $factory = Firm::factory()->for($user);
        $firm = ($package ? $factory->package($package) : $factory)->create();
        if ($verified) {
            $firm->forceFill(['verified_at' => now()])->save();
        }

        return $user->fresh();
    }

    protected function order(User $user, string $code, ?Listing $listing = null, ?BuyerDetails $buyer = null): Order
    {
        return app(OrderService::class)->create($user, $code, $listing, $buyer ?? BuyerDetails::private('127.0.0.1'));
    }

    protected function firmBuyer(): BuyerDetails
    {
        return BuyerDetails::firm('Test DOO', '100002887', '12345678', 'Glavna 1, Loznica', 'racun@example.com', '127.0.0.1');
    }

    protected function pay(Order $order): Order
    {
        app(OrderFulfillment::class)->markPaid($order, User::factory()->admin()->create());

        return $order->refresh();
    }
}
