<?php

namespace Tests\Feature\Firms;

use App\Enums\AccountType;
use App\Models\Firm;
use App\Models\User;
use App\Support\Firms\FirmCredits;
use App\Support\Firms\FirmPackage;
use Tests\TestCase;

class CreditsTest extends TestCase
{
    private function firm(string $package): Firm
    {
        $user = User::factory()->create(['account_type' => AccountType::Firm]);

        return Firm::factory()->package($package)->for($user)->create();
    }

    public function test_credits_are_used_up_and_reset_each_month(): void
    {
        $this->travelTo('2026-10-30 12:00:00');
        $firm = $this->firm('max');

        $this->assertTrue(FirmCredits::consume($firm, 'premium_xl'));
        $this->assertFalse(FirmCredits::consume($firm, 'premium_xl'));
        $this->assertTrue(FirmCredits::consume($firm, 'bumps'));
        $this->assertSame(['bumps' => 19, 'highlights' => 5, 'premium_xl' => 0], FirmCredits::remaining($firm));

        $this->travelTo('2026-11-01 08:00:00');
        $this->assertSame(['bumps' => 20, 'highlights' => 5, 'premium_xl' => 1], FirmCredits::remaining($firm));
    }

    public function test_no_credits_without_package_or_on_start(): void
    {
        $start = $this->firm('start');
        $this->assertFalse(FirmCredits::consume($start, 'bumps'));

        $expired = $this->firm('pro');
        $expired->forceFill(['package_ends_at' => now()->subDay()])->save();
        $this->assertFalse(FirmCredits::consume($expired, 'bumps'));
        $this->assertSame(['bumps' => 0, 'highlights' => 0, 'premium_xl' => 0], FirmCredits::remaining($expired));

        $this->expectException(\InvalidArgumentException::class);
        FirmCredits::consume($start, 'banner');
    }

    public function test_upgrade_during_the_month_raises_the_allowance(): void
    {
        $firm = $this->firm('pro');
        FirmCredits::consume($firm, 'bumps');

        FirmPackage::grant($firm, 'max', 'monthly');
        $this->assertSame(['bumps' => 19, 'highlights' => 5, 'premium_xl' => 1], FirmCredits::remaining($firm->fresh()));
    }

    public function test_grant_extends_a_running_term_and_starts_a_new_one(): void
    {
        $this->travelTo('2026-10-24 10:00:00');
        $firm = $this->firm('pro');
        $end = $firm->package_ends_at->copy();

        FirmPackage::grant($firm, 'pro', 'yearly');
        $this->assertTrue($firm->package_ends_at->equalTo($end->copy()->addYear()));

        FirmPackage::grant($firm, 'start', 'monthly');
        $this->assertSame('start', $firm->package);
        $this->assertTrue($firm->package_ends_at->equalTo(now()->addMonth()));

        FirmPackage::grantFreeYear($firm);
        $this->assertTrue($firm->free_program);
        $this->assertSame(FirmPackage::FREE, FirmPackage::code($firm));
        $this->assertTrue($firm->package_ends_at->equalTo(now()->addMonths(12)));
    }
}
