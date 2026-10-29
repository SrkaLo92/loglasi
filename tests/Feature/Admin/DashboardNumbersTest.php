<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\ListingStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Models\Listing;
use App\Models\User;
use App\Services\Admin\AdminStats;
use Livewire\Livewire;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

/** Every number on the dashboard, in the tabs and in the menu badges means the same thing and follows writes. */
class DashboardNumbersTest extends TestCase
{
    use ActsAsStaff;

    public function test_tiles_tabs_and_badges_agree_and_follow_approval(): void
    {
        $first = Listing::factory()->create(['status' => ListingStatus::Published, 'expires_at' => now()->addDays(5)]);
        $same = ['category_id' => $first->category_id, 'location_id' => $first->location_id];
        $pending = Listing::factory()->create($same + ['status' => ListingStatus::Pending, 'title' => 'Čeka']);
        Listing::factory()->create($same + ['status' => ListingStatus::Published, 'expires_at' => now()->subHour(), 'title' => 'Prošao rok']);
        Listing::factory()->create($same + ['status' => ListingStatus::Expired, 'title' => 'Istekao']);
        Listing::factory()->create($same + ['status' => ListingStatus::Sold, 'title' => 'Prodat']);
        Listing::factory()->create($same + ['status' => ListingStatus::Rejected, 'title' => 'Odbijen']);
        Listing::factory()->create($same + ['status' => ListingStatus::Draft, 'title' => '']);
        User::factory()->create(['account_type' => AccountType::Firm]);
        $this->signIn($admin = $this->admin());
        $stats = app(AdminStats::class);

        $c = $stats->counts();
        $this->assertEquals(['published' => 1, 'pending' => 1, 'expired' => 2, 'rejected' => 1, 'sold' => 1, 'drafts' => 1, 'all' => 6],
            array_intersect_key($c, array_flip(['published', 'pending', 'expired', 'rejected', 'sold', 'drafts', 'all'])));
        $this->assertSame($c['published'] + $c['pending'] + $c['expired'] + $c['rejected'] + $c['sold'], $c['all']);
        $this->assertSame(1, $c['firms']);
        $this->assertSame('1', ListingResource::getNavigationBadge());
        $this->assertSame(1, $stats->queue()['listings']);

        $list = Livewire::test(ListListings::class);
        foreach (['published' => 1, 'expired' => 2, 'sold' => 1, 'drafts' => 1, 'all' => 6, 'pending' => 1] as $tab => $count) {
            $list->set('activeTab', $tab)->assertCountTableRecords($count);
        }

        // Approve: the tile, the queue and the badge move together, at once.
        Livewire::test(ListListings::class)->set('activeTab', 'pending')->callTableAction('approve', $pending);
        $this->assertSame(0, $stats->queue()['listings']);
        $this->assertSame(0, $stats->counts()['pending']);
        $this->assertSame(2, $stats->counts()['published']);
        $this->assertNull(ListingResource::getNavigationBadge());

        // A listing posted on the site (no admin action) shows up right away too.
        Listing::factory()->create($same + ['status' => ListingStatus::Pending, 'title' => 'Nov']);
        $this->assertSame(1, $stats->queue()['listings']);
        $this->assertSame(1, $stats->counts()['pending']);
    }
}
