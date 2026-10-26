<?php

namespace Tests\Feature\Pages;

use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Support\Pages\Navigation;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    public function test_header_has_logo_search_login_and_post_button(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('img/brand/logo.svg', false)
            ->assertSee('role="search"', false)
            ->assertSee('action="'.url_slash('oglasi').'"', false)
            ->assertSee('name="keyword"', false)
            ->assertSee('<label class="lg-visually-hidden" for="lg-q">Šta tražite?</label>', false)
            ->assertSee('href="'.url_slash('login-registracija').'"', false)
            ->assertSee('Prijava')
            ->assertSee('Postavite oglas')
            ->assertSee('href="'.url_slash('postavi-oglas').'"', false)
            ->assertSee('Pređi na sadržaj');
    }

    public function test_logged_in_user_sees_my_account(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/')
            ->assertSee('Moj nalog')
            ->assertSee('href="'.url_slash('moj-nalog').'"', false)
            ->assertSee('aria-label="Moj nalog"', false);
    }

    public function test_search_selects_list_categories_and_places_from_database(): void
    {
        Category::factory()->create(['name' => 'Automobili', 'slug' => 'automobili', 'parent_id' => null]);
        Category::factory()->create(['name' => 'Neaktivna', 'slug' => 'neaktivna', 'is_active' => false]);
        Location::factory()->create(['name' => 'Banja Koviljača', 'slug' => 'banja-koviljaca']);

        $this->get('/')
            ->assertSee('<option value="automobili" >Automobili</option>', false)
            ->assertDontSee('Neaktivna')
            ->assertSee('Banja Koviljača')
            ->assertSee('<option value="">Sve kategorije</option>', false);
    }

    public function test_header_without_categories_hides_empty_selects(): void
    {
        $this->get('/')->assertDontSee('name="kategorija"', false)->assertDontSee('name="mesto"', false);
    }

    public function test_bottom_nav_items_and_active_state(): void
    {
        $html = $this->get('/')->getContent();

        foreach (['Početna', 'Pretraga', 'Postavi oglas', 'Poruke', 'Nalog'] as $label) {
            $this->assertStringContainsString('<span class="lg-mnav__naziv">'.$label.'</span>', $html);
        }
        $this->assertStringContainsString('aria-label="Početna strana"  aria-current="page"', $html);
        $this->assertStringContainsString('aria-label="Prijava ili moj nalog"', $html);
        $this->assertStringContainsString('<nav class="lg-mnav" aria-label="Glavna navigacija">', $html);
    }

    public function test_bottom_nav_unread_badge(): void
    {
        $html = view('partials.bottom-nav', ['unreadMessages' => 3])->render();

        $this->assertStringContainsString('aria-label="Poruke, 3 nepročitane"', $html);
        $this->assertStringContainsString('<span class="lg-mnav__broj" aria-hidden="true">3</span>', $html);
    }

    public function test_active_item_by_path(): void
    {
        $this->assertSame('pocetna', Navigation::aktivna('/'));
        $this->assertSame('pretraga', Navigation::aktivna('oglasi/automobili'));
        $this->assertSame('pretraga', Navigation::aktivna('oglas/golf-7'));
        $this->assertSame('postavi', Navigation::aktivna('postavi-oglas'));
        $this->assertSame('poruke', Navigation::aktivna('moj-nalog/poruke/5'));
        $this->assertSame('nalog', Navigation::aktivna('moj-nalog'));
        $this->assertSame('', Navigation::aktivna('cenovnik'));
    }

    public function test_fonts_are_self_hosted_and_preloaded(): void
    {
        $this->get('/')
            ->assertSee('<link rel="preload" href="'.asset('fonts/manrope-latin.woff2').'" as="font" type="font/woff2" crossorigin>', false)
            ->assertDontSee('fonts.googleapis.com', false);

        $this->assertFileExists(public_path('fonts/manrope-latin.woff2'));
        $this->assertFileExists(public_path('fonts/manrope-latin-ext.woff2'));
    }
}
