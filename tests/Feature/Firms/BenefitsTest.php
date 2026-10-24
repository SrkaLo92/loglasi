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
    private function firmUser(array $firm = [], string $package = 'pro'): User
    {
        $user = User::factory()->create(['account_type' => AccountType::Firm]);
        Firm::factory()->package($package)->for($user)->create($firm);

        return $user->load('firm');
    }

    private function benefits(): FirmBenefits
    {
        return app(FirmBenefits::class);
    }

    public function test_active_free_year_gets_every_benefit_but_advanced_stats(): void
    {
        $user = User::factory()->create(['account_type' => AccountType::Firm]);
        Firm::factory()->freeYear()->for($user)->create(['verified_at' => now()]);
        $user->load('firm')->firm->forceFill(['profile_views' => 10])->save();

        $flags = $this->benefits()->all($user);
        $this->assertSame(FirmBenefits::FLAGS, array_keys($flags));
        $this->assertFalse($flags['show_advanced_stats']);
        unset($flags['show_advanced_stats']);
        $this->assertNotContains(false, $flags);
        $this->assertSame('free', $this->benefits()->packageCode($user));
        $this->assertSame('Prvih 30 firmi', $this->benefits()->packageLabel($user));
        $this->assertNull($this->benefits()->listingLimit($user));
        $this->assertTrue($this->benefits()->hasPackage($user->firm));
    }

    public function test_expired_package_falls_back_but_keeps_firm_badge_and_data(): void
    {
        $user = $this->firmUser(['package_ends_at' => now()->subMinute(), 'website' => 'https://firma.rs']);
        $b = $this->benefits();

        $this->assertFalse($b->hasPackage($user));
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
        Settings::set('private_active_limit', 5);
        $make = function (string $package, array $firm = []) {
            $user = User::factory()->create(['account_type' => AccountType::Firm]);
            Firm::factory()->package($package)->for($user)->create($firm);

            return $user->load('firm');
        };

        $this->assertSame(20, $this->benefits()->listingLimit($make('start')));
        $this->assertSame(100, $this->benefits()->listingLimit($make('pro')));
        $this->assertNull($this->benefits()->listingLimit($make('max')));
        $this->assertSame(5, $this->benefits()->listingLimit($make('max', ['package_ends_at' => now()->subDay()])));
        $this->assertSame(5, $this->benefits()->listingLimit(User::factory()->create()));

        // The product row (admin) wins over the config default.
        Product::byCode('business_start_monthly')->update(['listing_limit' => 25]);
        $this->assertSame(25, $this->benefits()->listingLimit($make('start')));
        $this->assertSame(20, $this->benefits()->listingLimit($make('start', ['package_period' => 'yearly', 'package_ends_at' => now()->addYear()])));
    }

    public function test_advanced_stats_and_monthly_credits_by_package(): void
    {
        $start = $this->firmUser([], 'start');
        $pro = $this->firmUser([], 'pro');
        $max = $this->firmUser([], 'max');

        $this->assertFalse($this->benefits()->canShowAdvancedStats($start));
        $this->assertTrue($this->benefits()->canShowAdvancedStats($pro));
        $this->assertSame(['bumps' => 0, 'highlights' => 0, 'premium_xl' => 0], $this->benefits()->monthlyCredits($start));
        $this->assertSame(['bumps' => 10, 'highlights' => 2, 'premium_xl' => 0], $this->benefits()->monthlyCredits($pro));
        $this->assertSame(['bumps' => 20, 'highlights' => 5, 'premium_xl' => 1], $this->benefits()->monthlyCredits($max));
    }

    public function test_days_left_and_expiring_soon(): void
    {
        $this->travelTo('2026-10-23 10:00:00');
        $user = $this->firmUser(['package_ends_at' => '2026-11-06 09:00:00']);

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

    public function test_firm_row_of_account_turned_private_has_no_package(): void
    {
        $user = $this->firmUser();
        $user->forceFill(['account_type' => AccountType::Private])->save();
        $firm = Firm::with('user')->find($user->firm->id);

        $this->assertFalse($this->benefits()->hasPackage($firm));
    }

    public function test_service_is_a_singleton(): void
    {
        $this->assertSame(app(FirmBenefits::class), app(FirmBenefits::class));
        Listing::factory()->create(); // keep factories warm for the other tests
    }
}
