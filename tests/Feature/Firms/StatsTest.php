<?php

namespace Tests\Feature\Firms;

use App\Enums\AccountType;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\User;
use App\Support\Firms\FirmStats;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatsTest extends TestCase
{
    private function firmUser(array $firm = []): User
    {
        $user = User::factory()->create(['account_type' => AccountType::Firm]);
        Firm::factory()->for($user)->create($firm);

        return $user->load('firm');
    }

    private function stat(Listing $listing, string $date, array $values): void
    {
        DB::table('listing_daily_stats')->insert(['listing_id' => $listing->id, 'date' => $date] + $values + [
            'views' => 0, 'phone_reveals' => 0, 'messages' => 0, 'favorites' => 0,
        ]);
    }

    public function test_daily_series_fills_missing_days_and_sums_only_own_listings(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = $this->firmUser();
        $a = Listing::factory()->for($user)->create(['title' => 'Zimske gume']);
        $b = Listing::factory()->for($user)->create(['title' => 'Letnje gume']);
        $other = Listing::factory()->create();

        $this->stat($a, '2026-10-23', ['views' => 10, 'phone_reveals' => 2]);
        $this->stat($b, '2026-10-23', ['views' => 5, 'messages' => 1]);
        $this->stat($a, '2026-10-20', ['views' => 3, 'favorites' => 4]);
        $this->stat($a, '2026-09-01', ['views' => 1000]); // outside 30 days
        $this->stat($other, '2026-10-23', ['views' => 999]);

        $stats = FirmStats::daily($user->id, 30);
        $this->assertCount(30, $stats['daily']);
        $this->assertSame('2026-09-24', $stats['from']);
        $this->assertSame('2026-10-23', $stats['to']);
        $this->assertSame(['views' => 18, 'phone_reveals' => 2, 'messages' => 1, 'favorites' => 4], $stats['totals']);
        $this->assertSame(['date' => '2026-10-23', 'views' => 15, 'phone_reveals' => 2, 'messages' => 1, 'favorites' => 0], end($stats['daily']));

        $rows = FirmStats::perListing($user->id, 30);
        $this->assertSame(['Zimske gume', 'Letnje gume'], array_column($rows, 'title'));
        $this->assertSame(13, $rows[0]['views']);

        $this->assertSame(1018, FirmStats::daily($user->id, 90)['totals']['views']);
    }

    public function test_stats_page_renders_tiles_charts_and_tables(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = $this->firmUser();
        $listing = Listing::factory()->for($user)->create(['title' => 'Polovne gume 16"']);
        $deleted = Listing::factory()->for($user)->create(['title' => 'Stari oglas']);
        $this->stat($listing, '2026-10-22', ['views' => 1234, 'phone_reveals' => 7]);
        $this->stat($deleted, '2026-10-22', ['views' => 3]);
        $deleted->delete();

        $html = $this->actingAs($user)->get('/moj-nalog/firma/statistika/')->assertOk()
            ->assertSee('Statistika firme')
            ->assertSee('Poslednjih 30 dana')
            ->assertSee('1.237')
            ->assertSee('Polovne gume 16&quot;', false)
            ->assertSee(url_slash('oglas/'.$listing->slug), false)
            ->assertSee('Stari oglas')
            ->assertSee('(obrisan)')
            ->assertSee('22. 10.: 1237 pregleda')
            ->assertSee('Tabela po danima')
            ->getContent();

        $this->assertSame(4, substr_count($html, '<svg viewBox="0 0 600 180" role="img"'));
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('<script', str_replace('<script type="application/ld+json">', '', $html));

        $this->actingAs($user)->get('/moj-nalog/firma/statistika/?period=90')->assertOk()->assertSee('lg-btn--green" href="'.url_slash('moj-nalog/firma/statistika').'?period=90"  aria-current="page"', false);
        $this->actingAs($user)->get('/moj-nalog/firma/statistika/?period=7')->assertRedirect(url_slash('moj-nalog/firma/statistika'));
    }

    public function test_expired_biznis_sees_upsell_instead_of_numbers(): void
    {
        $user = $this->firmUser(['biznis_until' => now()->subDay()]);
        $listing = Listing::factory()->for($user)->create();
        $this->stat($listing, now()->toDateString(), ['views' => 77]);

        $this->actingAs($user)->get('/moj-nalog/firma/statistika/')->assertOk()
            ->assertSee('Statistika je deo Biznis paketa.')
            ->assertSee('Produžite Biznis paket')
            ->assertDontSee('Tabela po danima');
    }

    public function test_empty_stats_render(): void
    {
        $user = $this->firmUser();

        $this->actingAs($user)->get('/moj-nalog/firma/statistika/')->assertOk()
            ->assertSee('U ovom periodu oglasi firme još nemaju preglede.');
    }
}
