<?php

namespace Tests\Feature\Search;

use App\Models\SearchEntry;
use App\Services\SearchIndex;
use App\Support\Home\HomeSections;
use App\Support\Search\PremiumXl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PremiumXlTest extends SearchTestCase
{
    /** Number of wide Premium XL cards in the HTML. */
    private function xlCards(string $html): int
    {
        return preg_match_all('~<article class="[^"]*\blc--xl\b~', $html);
    }

    public function test_premium_xl_ranks_with_top_and_ends_with_the_period(): void
    {
        $plain = $this->listing(['title' => 'Običan', 'bumped_at' => now()]);
        $priority = $this->listing(['title' => 'Prioritetni', 'priority_until' => now()->addDays(3), 'bumped_at' => now()->subDay()]);
        $xl = $this->listing(['title' => 'Premium XL', 'premium_xl_until' => now()->addDays(2), 'bumped_at' => now()->subDays(3)]);
        $top = $this->listing(['title' => 'TOP', 'top_until' => now()->addDays(7), 'bumped_at' => now()->subDays(2)]);

        $this->assertSame(3, SearchEntry::find($xl->id)->featured);
        $this->assertSame(now()->addDays(2)->toDateString(), SearchEntry::find($xl->id)->featured_until->toDateString());
        $this->assertSame(3, $xl->fresh()->promotionRank());
        $this->assertSame(['TOP', 'Premium XL', 'Prioritetni', 'Običan'], array_column($this->getJson('/api/v1/listings')->json('items'), 'title'));
        $this->assertTrue(collect($this->getJson('/api/v1/listings')->json('items'))->firstWhere('title', 'Premium XL')['featured']);

        // The rebuild gives the same rank.
        $this->artisan('search:rebuild')->assertSuccessful();
        $this->assertSame(3, SearchEntry::find($xl->id)->featured);

        $this->travel(3)->days();
        app(SearchIndex::class)->refresh();
        $this->assertSame(0, SearchEntry::find($xl->id)->featured);
        $this->assertSame(3, SearchEntry::find($top->id)->featured);
        $this->assertSame(0, SearchEntry::find($priority->id)->featured);
        $this->assertSame(0, SearchEntry::find($plain->id)->featured);
    }

    public function test_rank_change_is_scheduled_at_the_earliest_end_of_equal_ranks(): void
    {
        $both = $this->listing(['top_until' => now()->addDays(7), 'premium_xl_until' => now()->addDay()]);

        $this->assertSame(3, SearchEntry::find($both->id)->featured);
        $this->assertSame(now()->addDay()->toDateString(), SearchEntry::find($both->id)->featured_until->toDateString());

        $this->travel(2)->days();
        app(SearchIndex::class)->refresh();
        $entry = SearchEntry::find($both->id);
        $this->assertSame(3, $entry->featured);
        $this->assertSame(now()->addDays(5)->toDateString(), $entry->featured_until->toDateString());
    }

    public function test_list_page_shows_the_wide_card_on_top(): void
    {
        $this->listing(['title' => 'Običan golf', 'bumped_at' => now()]);
        $this->listing([
            'title' => 'Premium Golf 7',
            'slug' => 'premium-golf-7',
            'description' => "Prvi vlasnik, servisna knjiga.\n\nRegistrovan do maja.",
            'premium_xl_until' => now()->addDays(7),
            'bumped_at' => now()->subWeek(),
        ]);

        $html = $this->get('/oglasi/automobili/')->assertOk()
            ->assertSee('Premium</span>', false)
            ->assertSee('Prvi vlasnik, servisna knjiga. Registrovan do maja.')
            ->assertSee('Kontaktirajte prodavca')
            ->getContent();

        $this->assertSame(1, $this->xlCards($html));
        $this->assertLessThan(strpos($html, 'Običan golf'), strpos($html, 'Premium Golf 7'));
        $this->assertSame(1, preg_match('~<article class="[^"]*\blc--xl\b[^"]*">\s*<a class="lc__link" href="[^"]*premium-golf-7~', $html));
    }

    public function test_at_most_one_wide_card_per_ten_listings(): void
    {
        foreach (range(1, 3) as $i) {
            $this->listing(['title' => 'XL '.$i, 'slug' => 'xl-'.$i, 'premium_xl_until' => now()->addDays(7), 'bumped_at' => now()->subMinutes($i)]);
        }
        foreach (range(1, 9) as $i) {
            $this->listing(['title' => 'Običan '.$i, 'bumped_at' => now()->subHours($i)]);
        }

        $html = $this->get('/oglasi/')->assertOk()->getContent();

        // 12 listings: two wide cards (XL 1 and XL 2); XL 3 keeps its rank on a normal card.
        $this->assertSame(2, $this->xlCards($html));
        $this->assertLessThan(strpos($html, 'Običan 1'), strpos($html, 'XL 3'));
        $this->assertSame(1, preg_match('~<article class="lc lc--list">\s*<a class="lc__link" href="[^"]*/xl-3/~', $html));
    }

    public function test_pick_and_excerpt(): void
    {
        config(['payments.premium_xl_every' => 10]);
        $xl = $this->listing(['premium_xl_until' => now()->addDay()]);
        $ended = $this->listing(['premium_xl_until' => now()->subMinute()]);

        $this->assertSame([$xl->id => true], PremiumXl::cards([$ended, $xl]));
        $this->assertSame([], PremiumXl::cards([$ended]));
        $this->assertSame('Opis sa razmacima.', PremiumXl::excerpt("  <b>Opis</b>\n sa   razmacima. "));
        $this->assertLessThanOrEqual(PremiumXl::EXCERPT + 1, mb_strlen(PremiumXl::excerpt(str_repeat('reč ', 200))));
        $this->assertSame('Pogledajte i javite se.', PremiumXl::excerpt('Pogledajte https://primer.rs/oglas?id=5 i www.test.rs javite se.'));
    }

    public function test_home_premium_section_shows_premium_home_listings_once(): void
    {
        $this->listing(['title' => 'Kuća na početnoj', 'premium_xl_until' => now()->addDays(7), 'premium_home_until' => now()->addDays(7)]);
        $this->listing(['title' => 'Samo XL u listi', 'premium_xl_until' => now()->addDays(7)]);
        $this->listing(['title' => 'Istekla početna', 'premium_xl_until' => now()->subDay(), 'premium_home_until' => now()->subDay()]);
        $expired = $this->listing(['title' => 'Istekao oglas', 'premium_home_until' => now()->addDays(7)]);
        DB::table('listings')->where('id', $expired->id)->update(['expires_at' => now()->subMinute()]);
        app(SearchIndex::class)->syncIds([$expired->id]);
        HomeSections::flush();

        $sections = HomeSections::get();
        $this->assertSame(['Kuća na početnoj'], $sections['premium']->pluck('title')->all());
        $this->assertNotContains('Kuća na početnoj', $sections['featured']->pluck('title')->all());
        $this->assertContains('Samo XL u listi', $sections['featured']->pluck('title')->all());

        $html = $this->get('/')->assertOk()->assertSee('Premium oglasi')->getContent();
        $premium = substr($html, strpos($html, 'id="hm-premium-title"'), strpos($html, 'id="hm-featured-title"') - strpos($html, 'id="hm-premium-title"'));
        $this->assertStringContainsString('Kuća na početnoj', $premium);
        $this->assertStringNotContainsString('Samo XL u listi', $premium);
        $this->assertSame(1, $this->xlCards($premium));
        $this->assertSame(1, $this->xlCards($html)); // the featured section uses normal cards
    }

    public function test_home_premium_section_is_capped_and_cached_as_ids_only(): void
    {
        foreach (range(1, 6) as $i) {
            $this->listing(['title' => 'Početna '.$i, 'premium_xl_until' => now()->addDays(7), 'premium_home_until' => now()->addDays(7)]);
        }
        HomeSections::flush();

        $this->assertCount(4, HomeSections::get()['premium']);

        $cached = Cache::get(HomeSections::CACHE_KEY);
        $this->assertCount(4, $cached['premium']);
        $this->assertContainsOnlyInt($cached['premium']);
    }

    public function test_home_hides_the_premium_section_when_empty(): void
    {
        $this->listing(['title' => 'Običan']);

        $this->get('/')->assertOk()->assertDontSee('Premium oglasi')->assertDontSee('hm-premium', false);
    }
}
