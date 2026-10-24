<?php

namespace Tests\Feature\Stranice;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use App\Support\Home\FooterSections;
use App\Support\Settings;
use Database\Seeders\ProductSeeder;
use Tests\TestCase;

class FooterAndFaqTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public function test_footer_has_contact_social_links_and_categories(): void
    {
        $category = Category::create(['name' => 'Automobili', 'slug' => 'automobili']);
        Listing::factory()->create(['category_id' => $category->id, 'location_id' => Location::factory(), 'user_id' => User::factory()]);
        FooterSections::flush();

        $this->get('/kontakt/')->assertOk()
            ->assertSee('href="tel:+381655415128"', false)
            ->assertSee('+381 65 541 5128')
            ->assertSee('mailto:'.Settings::get('contact_email'), false)
            ->assertSee('15300 Loznica, Srbija')
            ->assertSee('Prijavite se da biste primali najnovija ažuriranja i vesti')
            ->assertSee('Pratite nas')
            ->assertSee('https://www.tiktok.com/@loglasi.rs', false)
            ->assertSee('Korisni linkovi')
            ->assertSee('href="'.url_slash('politika-privatnosti').'"', false)
            ->assertSee('href="'.url_slash('brisanje-licnih-podataka').'"', false)
            ->assertSee('Brisanje ličnih podataka')
            ->assertSee('href="'.url_slash('oglasi/automobili').'"', false)
            ->assertSee('data-lg-najnoviji="'.url_slash('podnozje/najnoviji-oglasi').'"', false)
            ->assertSee('Alphasoft Development')
            ->assertSee('Sva prava zadržana.');
    }

    public function test_newest_listings_for_the_footer_come_as_json(): void
    {
        $category = Category::create(['name' => 'Automobili', 'slug' => 'automobili']);
        $listing = Listing::factory()->create(['title' => 'Golf 4 dizel', 'category_id' => $category->id, 'location_id' => Location::factory(), 'user_id' => User::factory()]);
        FooterSections::flush();

        $this->get('/kontakt/')->assertDontSee('Golf 4 dizel');

        $this->getJson('/podnozje/najnoviji-oglasi/')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertJsonPath('data.0.title', 'Golf 4 dizel')
            ->assertJsonPath('data.0.url', '/oglas/'.$listing->slug.'/')
            ->assertJsonCount(1, 'data');
    }

    public function test_data_deletion_page_and_old_addresses(): void
    {
        $this->get('/brisanje-licnih-podataka/')->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('Brisanje ličnih podataka')
            ->assertSee('href="/moj-nalog/"', false)
            ->assertSee(Settings::get('contact_email'));

        $this->get('/birsanje-licnih-podataka/')->assertRedirect(url_slash('brisanje-licnih-podataka'))->assertStatus(301);
        $this->get('/brisanje-podataka/')->assertRedirect(url_slash('brisanje-licnih-podataka'))->assertStatus(301);
    }

    public function test_contact_details_come_from_settings(): void
    {
        Settings::set('contact_phone', '+381641234567');
        Settings::set('contact_address', '');

        $this->get('/faq/')->assertOk()
            ->assertSee('href="tel:+381641234567"', false)
            ->assertDontSee('15300 Loznica, Srbija');
    }

    public function test_faq_has_help_box_and_faq_page_schema(): void
    {
        $response = $this->get('/faq/')->assertOk()
            ->assertSee('Niste pronašli odgovor?')
            ->assertSee('href="'.url_slash('kontakt').'#forma"', false)
            ->assertSee('<details class="lgx-faq__stavka"  open >', false);

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $m);
        $faq = collect($m[1])->map(fn ($json) => json_decode($json, true))
            ->flatMap(fn ($data) => isset($data['@graph']) ? $data['@graph'] : [$data])
            ->firstWhere('@type', 'FAQPage');

        $this->assertNotNull($faq);
        $this->assertNotEmpty($faq['mainEntity']);
        $this->assertSame('Question', $faq['mainEntity'][0]['@type']);
        $this->assertStringNotContainsString('<', $faq['mainEntity'][0]['acceptedAnswer']['text']);
    }
}
