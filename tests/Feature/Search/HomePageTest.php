<?php

namespace Tests\Feature\Search;

use App\Models\User;
use App\Support\Home\HomeSections;
use App\Support\Settings;

class HomePageTest extends SearchTestCase
{
    public function test_home_shows_the_sections(): void
    {
        foreach (range(1, 3) as $i) {
            $this->listing(['title' => 'Novi oglas '.$i, 'bumped_at' => now()->subMinutes($i)]);
        }
        $this->listing(['title' => 'Istaknuti stan', 'category_id' => $this->homes->id, 'featured_until' => now()->addDay()]);

        $response = $this->get('/')->assertOk();

        $response->assertSee('Oglasi za Loznicu i okolinu')
            ->assertSee('Često se traži:')
            ->assertSee('Kategorije')
            ->assertSee('Sve kategorije')
            ->assertSee('Istaknuti oglasi')
            ->assertSee('Istaknuti stan')
            ->assertSee('Najnoviji oglasi')
            ->assertSee('Novi oglas 1')
            ->assertSee('Pogledajte sva 4 oglasa')
            ->assertSee('Najtraženije kategorije')
            ->assertSee('role="tab"', false)
            ->assertSee('Firme iz Loznice na jednom mestu')
            ->assertSee('Zašto loglasi.rs')
            ->assertSee('Direktno sa prodavcem')
            ->assertSee('O sajtu loglasi.rs');

        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Novi oglas 2'), strpos($html, 'Novi oglas 1'));
    }

    public function test_latest_section_is_plain_newest(): void
    {
        $this->listing(['title' => 'Stari istaknut', 'bumped_at' => now()->subDays(5), 'featured_until' => now()->addDay()]);
        $this->listing(['title' => 'Novi običan']);

        $sections = HomeSections::get();

        $this->assertSame(['Novi običan', 'Stari istaknut'], $sections['latest']->pluck('title')->all());
        $this->assertSame(['Stari istaknut'], $sections['featured']->pluck('title')->all());
    }

    public function test_empty_site_hides_featured_and_shows_a_call_to_action(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('Istaknuti oglasi')
            ->assertSee('Još nema oglasa')
            ->assertDontSee('Najtraženije kategorije');
    }

    public function test_new_listings_show_at_once_despite_the_cache(): void
    {
        $this->listing(['title' => 'Prvi oglas']);
        $this->get('/')->assertSee('Prvi oglas');

        $this->listing(['title' => 'Drugi oglas']);
        $this->get('/')->assertSee('Drugi oglas');
    }

    public function test_firm_strip_needs_the_minimum_number_of_firms(): void
    {
        User::factory()->firm()->count(2)->create();
        $this->get('/')->assertDontSee('Na loglasi.rs su već');

        User::factory()->firm()->create();
        $this->get('/')->assertSee('Na loglasi.rs su već 3 firme');
    }

    public function test_settings_change_texts(): void
    {
        $this->listing(['title' => 'Golf']);
        Settings::set('home.intro_terms', 'bicikl, Automobili');
        Settings::set('home.latest_title', 'Sveže iz Loznice');
        Settings::set('home.about', 'Naš **tekst** o sajtu.');

        $this->get('/')->assertOk()
            ->assertSee('href="'.url_slash('/oglasi').'?keyword=bicikl"', false)
            ->assertSee('href="'.url_slash('/oglasi/automobili').'"', false)
            ->assertSee('Sveže iz Loznice')
            ->assertSee('<strong>tekst</strong>', false);
    }
}
