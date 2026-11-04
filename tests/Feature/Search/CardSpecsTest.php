<?php

namespace Tests\Feature\Search;

use App\Models\Category;
use App\Models\Field;
use App\Models\Listing;
use App\Models\Location;
use App\Services\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardSpecsTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_estate_card_says_what_the_numbers_mean_and_who_sells(): void
    {
        $flats = Category::factory()->create(['name' => 'Stanovi | prodaja', 'slug' => 'stanovi-prodaja']);
        $place = Location::factory()->create(['name' => 'Loznica', 'slug' => 'loznica']);
        $fields = [
            'naselje' => ['label' => 'Naselje / ulica', 'type' => 'text', 'value' => 'Lagator'],
            'velicina_nekretnine' => ['label' => 'Kvadratura', 'type' => 'number', 'unit' => 'm²', 'value' => '77'],
            'sobe' => ['label' => 'Broj soba', 'type' => 'number', 'value' => '3'],
            'sprat' => ['label' => 'Sprat', 'type' => 'select', 'options' => [['value' => '4', 'label' => '4. sprat']], 'value' => '4'],
        ];
        $listing = Listing::factory()->for($flats)->for($place)->create(['title' => 'Trosoban stan']);
        $sort = 0;
        foreach ($fields as $key => $def) {
            $value = $def['value'];
            unset($def['value']);
            $field = Field::create(['key' => $key] + $def);
            $flats->fields()->attach($field->id, ['required' => false, 'sort' => $sort++]);
            $listing->fieldValues()->create(['field_id' => $field->id, 'value' => $value]);
        }
        app(Catalog::class)->flush();

        $this->get('/oglasi/stanovi-prodaja/')->assertOk()
            ->assertSee('77 m² · 3 sobe · 4. sprat · Vlasnik')
            ->assertSee('Lagator, Loznica')
            ->assertDontSee('login-registracija/?nazad', false);
    }
}
