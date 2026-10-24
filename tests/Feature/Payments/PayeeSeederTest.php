<?php

namespace Tests\Feature\Payments;

use App\Support\Settings;
use Database\Seeders\PayeeSeeder;
use Tests\TestCase;

class PayeeSeederTest extends TestCase
{
    public function test_fills_only_empty_payee_settings(): void
    {
        Settings::set('payment_account', '160-0000000000000-00');

        $this->seed(PayeeSeeder::class);

        $this->assertSame('160-0000000000000-00', Settings::get('payment_account'));
        $this->assertSame('115879910', Settings::get('payment_pib'));
        $this->assertStringStartsWith('SRĐAN MARINKOVIĆ PR ALPHASOFT DEV', Settings::get('payment_recipient'));
    }
}
