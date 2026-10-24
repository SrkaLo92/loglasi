<?php

namespace Tests\Feature\Notifications;

use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\SavedSearchMatcher;
use App\Support\Settings;
use Tests\TestCase;

class SavedSearchMatcherTest extends TestCase
{
    use BuildsCatalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildCatalog();
        Settings::set('eur_rate', 117.2);
    }

    private function search(array $query, ?User $user = null): SavedSearch
    {
        return SavedSearch::create(['user_id' => ($user ?? User::factory()->create())->id, 'query' => $query, 'notify' => true]);
    }

    /** @return list<int> */
    private function matched(SavedSearch $search, Listing ...$listings): array
    {
        $listings = Listing::query()->whereKey(array_map(fn ($l) => $l->id, $listings))->with('fieldValues')->get();

        return app(SavedSearchMatcher::class)->match($listings, [$search])[$search->id] ?? [];
    }

    public function test_parent_category_includes_subcategories(): void
    {
        $car = $this->car();
        $house = Listing::factory()->create(['category_id' => $this->nekretnine->id]);

        $this->assertSame([$car->id], $this->matched($this->search(['kategorija' => 'vozila']), $car, $house));
    }

    public function test_location_is_exact(): void
    {
        $here = $this->car();
        $there = $this->car(['location_id' => $this->banja->id]);

        $this->assertSame([$here->id], $this->matched($this->search(['mesto' => 'loznica']), $here, $there));
    }

    public function test_price_bounds_use_eur_and_skip_negotiable(): void
    {
        $cheap = $this->car(['price' => 4000]);
        $rsd = $this->car(['price' => 468800, 'currency' => 'RSD']); // 4.000 €
        $expensive = $this->car(['price' => 9000]);
        $negotiable = $this->car(['price' => null]);

        $search = $this->search(['price_max' => 5000, 'currency' => 'EUR']);
        $this->assertEqualsCanonicalizing([$cheap->id, $rsd->id], $this->matched($search, $cheap, $rsd, $expensive, $negotiable));

        $inRsd = $this->search(['price_min' => 500000, 'currency' => 'RSD']);
        $this->assertSame([$expensive->id], $this->matched($inRsd, $cheap, $rsd, $expensive, $negotiable));
    }

    public function test_keyword_ignores_diacritics_and_matches_word_starts(): void
    {
        $golf = $this->car(['title' => 'VW Golf 7 karavan', 'description' => 'Prvi vlasnik, Lešnica']);
        $other = $this->car(['title' => 'Škoda Octavia', 'description' => 'Odlično stanje']);

        $this->assertSame([$golf->id], $this->matched($this->search(['keyword' => 'golf lesnic']), $golf, $other));
        $this->assertSame([$other->id], $this->matched($this->search(['keyword' => 'skoda']), $golf, $other));
        $this->assertSame([], $this->matched($this->search(['keyword' => 'olf']), $golf, $other)); // not a word start
    }

    public function test_field_filters(): void
    {
        $skoda = $this->car([], 'skoda', 150000);
        $vw = $this->car([], 'vw', 90000);
        $noFields = $this->car();

        $search = $this->search(['kategorija' => 'automobili', 'f' => ['marka' => 'skoda']]);
        $this->assertSame([$skoda->id], $this->matched($search, $skoda, $vw, $noFields));

        $range = $this->search(['kategorija' => 'automobili', 'f' => ['kilometraza_do' => '100000']]);
        $this->assertSame([$vw->id], $this->matched($range, $skoda, $vw, $noFields));
    }

    public function test_own_listings_never_match(): void
    {
        $user = User::factory()->create();
        $own = $this->car(['user_id' => $user->id]);
        $foreign = $this->car();

        $this->assertSame([$foreign->id], $this->matched($this->search(['kategorija' => 'vozila'], $user), $own, $foreign));
    }

    public function test_search_with_removed_category_matches_nothing(): void
    {
        $car = $this->car();
        $this->assertSame([], $this->matched($this->search(['kategorija' => 'ne-postoji']), $car));
    }

    public function test_many_searches_are_matched_in_one_pass(): void
    {
        $car = $this->car(['title' => 'Golf']);
        $searches = collect([
            $this->search(['kategorija' => 'vozila']),
            $this->search(['kategorija' => 'nekretnine']),
            $this->search(['keyword' => 'golf']),
            $this->search(['mesto' => 'banja-koviljaca']),
        ]);

        $result = app(SavedSearchMatcher::class)->match(Listing::query()->with('fieldValues')->get(), $searches);

        ksort($result);
        $this->assertSame([$searches[0]->id => [$car->id], $searches[2]->id => [$car->id]], $result);
    }
}
