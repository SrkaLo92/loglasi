<?php

namespace Tests\Feature\Firms;

use App\Enums\AccountType;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\Product;
use App\Models\User;
use App\Services\FirmBenefits;
use App\Support\Settings;
use Tests\TestCase;

class BenefitsTest extends TestCase
{
    private function firmUser(array $firm = []): User
    {
        $user = User::factory()->create(['account_type' => AccountType::Firm]);
        Firm::factory()->for($user)->create($firm);

        return $user->load('firm');
    }

    private function benefits(): FirmBenefits
    {
        return app(FirmBenefits::class);
    }

    public function test_active_biznis_gets_every_benefit(): void
    {
        $user = $this->firmUser(['verified_at' => now()]);
        $user->firm->forceFill(['founding_free' => true, 'profile_views' => 10])->save();

        $flags = $this->benefits()->all($user);
        $this->assertSame(FirmBenefits::FLAGS, array_keys($flags));
        $this->assertNotContains(false, $flags);
        $this->assertTrue($this->benefits()->hasBiznis($user->firm));
    }

    public function test_expired_biznis_falls_back_but_keeps_firm_badge_and_data(): void
    {
        $user = $this->firmUser(['biznis_until' => now()->subMinute(), 'website' => 'https://firma.rs']);
        $b = $this->benefits();

        $this->assertFalse($b->hasBiznis($user));
        $this->assertTrue($b->isExpired($user));
        $this->assertTrue($b->canShowFirmBadge($user));
        foreach (['canShowLogo', 'canShowCover', 'canShowDetails', 'canShowStats', 'isIndexable', 'isListedInDirectory', 'canShowMessengerButtons', 'canShowWebsite'] as $method) {
            $this->assertFalse($b->{$method}($user), $method);
        }
        $this->assertSame('https://firma.rs', $user->firm->fresh()->website);
    }

    public function test_private_user_gets_nothing(): void
    {
        $user = User::factory()->create();
        $flags = $this->benefits()->all($user);

        $this->assertNotContains(true, $flags);
        $this->assertNull($this->benefits()->firmOf($user));
    }

    public function test_listing_limit_follows_package(): void
    {
        Settings::set('private_monthly_limit', 10);
        Product::factory()->create(['code' => Product::BIZNIS_12M, 'listing_limit' => null]);
        $active = $this->firmUser();
        $expired = $this->firmUser(['biznis_until' => now()->subDay()]);

        $this->assertNull($this->benefits()->listingLimit($active));
        $this->assertSame(10, $this->benefits()->listingLimit($expired));
        $this->assertSame(10, $this->benefits()->listingLimit(User::factory()->create()));
    }

    public function test_days_left_and_expiring_soon(): void
    {
        $this->travelTo('2026-10-23 10:00:00');
        $user = $this->firmUser(['biznis_until' => '2026-11-06 09:00:00']);

        $this->assertSame(14, $this->benefits()->daysLeft($user));
        $this->assertTrue($this->benefits()->isExpiringSoon($user));
        $this->assertFalse($this->benefits()->isExpiringSoon($user, 13));
        $this->assertNull($this->benefits()->daysLeft(User::factory()->create()));
    }

    public function test_verified_badge_and_profile_views_threshold(): void
    {
        $user = $this->firmUser();
        $user->firm->forceFill(['profile_views' => 9])->save();

        $this->assertFalse($this->benefits()->canShowVerifiedBadge($user));
        $this->assertFalse($this->benefits()->canShowProfileViews($user));
    }

    public function test_firm_row_of_account_turned_private_has_no_biznis(): void
    {
        $user = $this->firmUser();
        $user->forceFill(['account_type' => AccountType::Private])->save();
        $firm = Firm::with('user')->find($user->firm->id);

        $this->assertFalse($this->benefits()->hasBiznis($firm));
    }

    public function test_service_is_a_singleton(): void
    {
        $this->assertSame(app(FirmBenefits::class), app(FirmBenefits::class));
        Listing::factory()->create(); // keep factories warm for the other tests
    }
}
