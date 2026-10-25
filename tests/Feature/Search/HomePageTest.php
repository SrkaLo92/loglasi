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
            ->assertDontSee('Često se traži:')
            ->assertSee('aria-label="Glavne kategorije"', false)
            ->assertSee('Popularno')
            ->assertSee('Sve kategorije')
            ->assertSee('Istaknuti oglasi')
            ->assertSee('Istaknuti stan')
            ->assertSee('Najnoviji oglasi')
            ->assertSee('Novi oglas 1')
            ->assertSee('Pogledajte sva 4 oglasa')
            ->assertSee('Najtraženije kategorije')
            ->assertSee('role="tab"', false)
            ->assertSee('Zašto izabrati nas')
            ->assertSee('Sigurnost i poverenje')
            ->assertSee('Podrška korisnicima')
            ->assertSee('Privatna lica imaju do 10 aktivnih oglasa besplatno')
            ->assertSee('Najčešća pitanja')
            ->assertSee('<details class="hm-faq__stavka" name="hm-faq">', false)
            ->assertSee('href="'.url_slash('faq').'"', false)
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

    public function test_firm_section_shows_only_when_firms_exist(): void
    {
        $this->get('/')->assertDontSee('id="hm-firms-title"', false)->assertDontSee('Prijavite svoju firmu');

        $user = User::factory()->firm()->create();
        $user->firm->update(['name' => 'Auto delovi Drina', 'activity' => 'Auto delovi', 'location_id' => $this->banja->id]);
        $this->listing(['user_id' => $user->id]);
        $this->listing(['user_id' => $user->id]);
        $this->listing(['user_id' => $user->id, 'status' => 'expired', 'expires_at' => now()->subDay()]);
        Settings::set('founding_firms_total', 30);
        HomeSections::flush();

        $this->get('/')->assertOk()
            ->assertSee('Firme iz Loznice')
            ->assertSee('Auto delovi Drina')
            ->assertSee('Auto delovi · Banja Koviljača')
            ->assertSee('2 oglasa')
            ->assertSee('href="'.url_slash('korisnik/'.$user->slug).'"', false)
            ->assertSee('Imate radnju ili servis u Loznici?')
            ->assertSee('Prvih 30 firmi koristi loglasi.rs besplatno 12 meseci.')
            ->assertSee('Prijavite svoju firmu')
            ->assertDontSee('Founder');
    }

    public function test_settings_change_texts(): void
    {
        $this->listing(['title' => 'Golf']);
        Settings::set('home.main_categories', 'automobili');
        Settings::set('home.latest_title', 'Sveže iz Loznice');
        Settings::set('home.about', 'Naš **tekst** o sajtu.');

        $this->get('/')->assertOk()
            ->assertSee('class="hm-main__tile hm-main__tile--blue" href="'.url_slash('/oglasi/automobili').'"', false)
            ->assertSee('Sveže iz Loznice')
            ->assertSee('<strong>tekst</strong>', false);
    }
}
