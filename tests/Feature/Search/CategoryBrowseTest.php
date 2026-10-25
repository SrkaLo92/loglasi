<?php

namespace Tests\Feature\Search;

use App\Models\Category;
use App\Services\Catalog;

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
