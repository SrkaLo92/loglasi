<?php

namespace Tests\Feature\Search;

use App\Models\Category;
use App\Services\Catalog;
use App\Support\Settings;

class CategoryBrowseTest extends SearchTestCase
{
    public function test_all_categories_sheet_has_search_index_most_wanted_and_themes(): void
    {
        $this->listing(['category_id' => $this->cars->id]);
        $this->listing(['category_id' => $this->cars->id]);

        $html = $this->get('/')->assertOk()
            ->assertSee('id="lg-cats"', false)
            ->assertSee('data-lg-cats', false)
            ->assertSee('Najtraženije')
            ->assertSee('Pretražite kategoriju')
            ->getContent();
        $this->assertStringContainsString('"n":"Automobili","p":"Vozila"', $html);

        $this->getJson('/api/v1/categories/browse')->assertOk()
            ->assertJsonPath('popular.0.slug', 'automobili')
            ->assertJsonPath('popular.0.parent_name', 'Vozila')
            ->assertJsonPath('popular.0.count', 2)
            ->assertJsonStructure(['themes' => [['name', 'items' => [['id', 'slug', 'name', 'icon', 'count']]]]]);
    }

    public function test_main_categories_for_the_home_tiles_follow_the_admin_setting(): void
    {
        $free = Category::create(['name' => 'Besplatne stvari', 'slug' => 'besplatne-stvari']);
        app(Catalog::class)->flush();
        Settings::set('home.main_categories', 'besplatne-stvari, nema-ove, automobili');

        $this->getJson('/api/v1/categories/browse')->assertOk()
            ->assertJsonCount(2, 'main')
            ->assertJsonPath('main.0.id', $free->id)
            ->assertJsonPath('main.0.icon', 'gift_heart')
            ->assertJsonPath('main.0.accent', 'red')
            ->assertJsonPath('main.0.color', 'red')
            ->assertJsonPath('main.0.tagline', 'Poklonite nekome')
            ->assertJsonPath('main.1.slug', 'automobili')
            ->assertJsonPath('main.1.accent', null)
            ->assertJsonPath('main.1.color', 'blue');
    }

    public function test_popular_is_the_admin_list_not_the_biggest_categories(): void
    {
        $this->listing(['category_id' => $this->cars->id]);
        Settings::set('home.popular_categories', 'nekretnine, nema-ove');

        $this->getJson('/api/v1/categories/browse')->assertOk()
            ->assertJsonCount(1, 'popular')
            ->assertJsonPath('popular.0.slug', 'nekretnine')
            ->assertJsonPath('popular.0.color', 'orange');
    }

    public function test_every_category_and_group_carries_the_colour_of_its_group(): void
    {
        $parts = Category::create(['name' => 'Delovi', 'slug' => 'delovi-za-automobile', 'parent_id' => $this->cars->id]);
        $phones = Category::create(['name' => 'Mobilni telefoni', 'slug' => 'mobilni-telefoni']);
        app(Catalog::class)->flush();
        Settings::set('home.popular_categories', 'delovi-za-automobile, mobilni-telefoni');

        $this->getJson('/api/v1/categories/browse')->assertOk()
            ->assertJsonPath('popular.0.id', $parts->id)
            ->assertJsonPath('popular.0.color', 'blue')
            ->assertJsonPath('popular.1.id', $phones->id)
            ->assertJsonPath('popular.1.color', 'indigo');

        $this->get('/')->assertOk()->assertSee('hm-pop__icon lg-tone--indigo', false)->assertSee('lgc-tile__media lg-tone--blue', false);
    }

    public function test_phone_filter_rows_show_the_category_and_its_groups(): void
    {
        $bikes = Category::create(['name' => 'Motocikli', 'slug' => 'motocikli', 'parent_id' => $this->vehicles->id]);
        app(Catalog::class)->flush();
        $this->listing(['category_id' => $this->cars->id]);
        $this->listing(['category_id' => $bikes->id]);

        $this->get('/oglasi/vozila/')->assertOk()
            ->assertSee('class="sr-row sr-mcat"', false)
            ->assertSee('data-sr-key="grupa"', false)
            ->assertSee('Sve u kategoriji Vozila')
            ->assertSee('Motocikli');

        $html = $this->get('/oglasi/automobili/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~aria-current="page"\s*>Automobili <span class="sr-links__n">~', $html);

        $this->get('/oglasi/automobili/?keyword=golf')->assertSee('<meta name="lg-recent" content="golf | Automobili">', false);
        $this->get('/oglasi/')->assertDontSee('name="lg-recent"', false);
    }
}
