<?php

namespace Tests\Feature\Firms;

use App\Models\Firm;
use App\Models\User;
use App\Support\Firms\FirmCredits;
use App\Support\Firms\FirmPackage;
use Tests\TestCase;

/** A package bought during the free first year starts when the free year ends. */
class FreeYearPackageTest extends TestCase
{
    private function freeFirm(): Firm
    {
        $user = User::factory()->create(['account_type' => 'firm']);
        $firm = Firm::factory()->for($user)->create();
        FirmPackage::grantFreeYear($firm);

        return $firm->fresh();
    }

    public function test_package_bought_in_the_free_year_is_queued_after_it(): void
    {
        $firm = $this->freeFirm();
        $freeEnds = $firm->free_program_ends_at->copy();

        FirmPackage::grant($firm, 'pro', 'monthly');
        $firm->refresh();

        $this->assertSame(FirmPackage::FREE, FirmPackage::code($firm));
        $this->assertTrue($firm->onFreeYear());
        $this->assertSame($freeEnds->toDateTimeString(), $firm->free_program_ends_at->toDateTimeString());
        $this->assertSame($freeEnds->toDateTimeString(), $firm->package_starts_at->toDateTimeString());
        $this->assertSame($freeEnds->copy()->addMonth()->toDateTimeString(), $firm->package_ends_at->toDateTimeString());
        // Free-year credits while it lasts.
        $this->assertSame(5, FirmCredits::remaining($firm)['bumps']);

        $this->travelTo($freeEnds->copy()->addDay());
        $firm->refresh();
        $this->assertFalse($firm->onFreeYear());
        $this->assertSame('pro', FirmPackage::code($firm));
        $this->assertTrue(FirmPackage::isActive($firm));
    }

    public function test_buying_the_same_package_twice_in_the_free_year_extends_the_queued_term(): void
    {
        $firm = $this->freeFirm();
        $freeEnds = $firm->free_program_ends_at->copy();

        FirmPackage::grant($firm, 'start', 'yearly');
        FirmPackage::grant($firm->refresh(), 'start', 'yearly');
        $firm->refresh();

        $this->assertSame($freeEnds->copy()->addYears(2)->toDateTimeString(), $firm->package_ends_at->toDateTimeString());
        $this->assertSame(FirmPackage::FREE, FirmPackage::code($firm));
    }

    public function test_free_year_ends_without_a_package_when_nothing_was_bought(): void
    {
        $firm = $this->freeFirm();
        $this->travelTo($firm->free_program_ends_at->copy()->addMinute());

        $this->assertNull(FirmPackage::code($firm->refresh()));
        $this->assertFalse(FirmPackage::isActive($firm));
    }

    public function test_package_outside_the_free_year_still_starts_now(): void
    {
        $user = User::factory()->create(['account_type' => 'firm']);
        $firm = Firm::factory()->for($user)->create();

        FirmPackage::grant($firm, 'max', 'monthly');
        $firm->refresh();

        $this->assertSame('max', FirmPackage::code($firm));
        $this->assertFalse((bool) $firm->free_program);
        $this->assertNull($firm->free_program_ends_at);
        $this->assertEqualsWithDelta(now()->addMonth()->timestamp, $firm->package_ends_at->timestamp, 5);
    }
}
