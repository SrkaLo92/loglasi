<?php

namespace Tests\Feature\Search;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Services\Catalog;
use App\Services\ListingFields;
use App\Support\Catalog\CatalogDefinition;
use Illuminate\Validation\ValidationException;

/** Model depends on make (polovniautomobili style), on the API, the list page, the posting form and the fields. */
class DependentFieldsTest extends SearchTestCase
{
    private Category $limuzina;

    private Category $karavan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->limuzina = Category::create(['name' => 'Automobili | Limuzina', 'slug' => 'automobili-limuzina', 'parent_id' => $this->cars->id]);
        $this->karavan = Category::create(['name' => 'Automobili | Karavan', 'slug' => 'automobili-karavan', 'parent_id' => $this->cars->id]);
        (new CatalogDefinition)->apply();
        app(Catalog::class)->flush();
    }

    private function car(string $title, Category $category, array $fields): Listing
    {
        $listing = $this->listing(['title' => $title, 'category_id' => $category->id]);
        app(ListingFields::class)->save($listing, app(ListingFields::class)->validate($category, $fields + ['stanje' => 'korisceno']));

        return $listing;
    }

    private function errors(Category $category, array $input): array
    {
        try {
            app(ListingFields::class)->validate($category, $input + ['stanje' => 'korisceno']);
        } catch (ValidationException $e) {
            return array_map(fn ($m) => $m[0], $e->errors());
        }

        return [];
    }

    public function test_api_describes_the_dependency(): void
    {
        $fields = collect($this->getJson('/api/v1/categories/'.$this->limuzina->id.'/fields')->assertOk()->json())->keyBy('key');

        $this->assertNull($fields['marka_automobila']['depends_on']);
        $this->assertSame('marka_automobila', $fields['model_automobila']['depends_on']);
        $this->assertContains(['value' => 'audi-a4', 'label' => 'A4', 'parent' => 'audi'], $fields['model_automobila']['options']);
        $this->assertArrayNotHasKey('parent', $fields['marka_automobila']['options'][0]);
        $this->assertSame('year', $fields['godina']['type']);
        $this->assertSame('km', $fields['kilometraza']['unit']);

        // The main category has no fields of its own but filters by the ones of the body types.
        $parent = collect($this->getJson('/api/v1/categories/'.$this->cars->id.'/fields')->assertOk()->json())->keyBy('key');
        $this->assertSame('marka_automobila', $parent->keys()->first());
        $this->assertFalse($parent['marka_automobila']['required']);
        $this->assertSame('marka_automobila', $parent['model_automobila']['depends_on']);
    }

    public function test_model_must_belong_to_the_make(): void
    {
        $base = ['marka_automobila' => 'audi', 'godina' => '2009'];

        $this->assertSame([], $this->errors($this->limuzina, $base + ['model_automobila' => 'audi-a4']));
        $this->assertSame(
            ['fields.model_automobila' => 'Izabrana vrednost za „Model” ne pripada izabranoj vrednosti polja „Marka”.'],
            $this->errors($this->limuzina, $base + ['model_automobila' => 'bmw-320']),
        );
        $this->assertEquals(
            ['fields.model_automobila' => 'Prvo izaberite polje „Marka”.', 'fields.marka_automobila' => 'Polje „Marka” je obavezno.'],
            $this->errors($this->limuzina, ['godina' => '2009', 'model_automobila' => 'audi-a4']),
        );
        $this->assertArrayHasKey('fields.model_automobila', $this->errors($this->limuzina, $base + ['model_automobila' => 'a4']));
    }

    public function test_numbers_are_read_as_people_type_them(): void
    {
        $values = app(ListingFields::class)->validate($this->limuzina, [
            'marka_automobila' => 'audi', 'godina' => '2009.', 'kilometraza' => '180.000 km', 'kubikaza' => '1 968', 'snaga' => '103', 'stanje' => 'korisceno',
        ]);

        $this->assertSame('2009', $values['godina']);
        $this->assertSame('180000', $values['kilometraza']);
        $this->assertSame('1968', $values['kubikaza']);

        $listing = $this->car('Audi A4', $this->limuzina, ['marka_automobila' => 'audi', 'godina' => '2009', 'snaga' => '103', 'kilometraza' => '180.000']);
        $display = array_column(app(ListingFields::class)->values($listing->fresh()->load('fieldValues')), 'display', 'key');
        $this->assertSame('103 kW (140 KS)', $display['snaga']);
        $this->assertSame('180.000 km', $display['kilometraza']);
        $this->assertSame('Audi', $display['marka_automobila']);
    }

    public function test_main_category_filters_by_make_and_model_and_ranges(): void
    {
        $this->car('Audi A4 2009', $this->limuzina, ['marka_automobila' => 'audi', 'model_automobila' => 'audi-a4', 'godina' => '2009', 'kilometraza' => '214000']);
        $this->car('Audi A6 Avant', $this->karavan, ['marka_automobila' => 'audi', 'model_automobila' => 'audi-a6', 'godina' => '2015', 'kilometraza' => '150000']);
        $this->car('Golf 4', $this->karavan, ['marka_automobila' => 'volkswagen', 'model_automobila' => 'volkswagen-golf-4', 'godina' => '2002']);

        $c = 'category='.$this->cars->id;
        $this->assertEqualsCanonicalizing(['Audi A4 2009', 'Audi A6 Avant'], $this->apiTitles($c.'&f[marka_automobila]=audi'));
        $this->assertSame(['Golf 4'], $this->apiTitles($c.'&f[model_automobila]=volkswagen-golf-4'));
        $this->assertSame(['Audi A6 Avant'], $this->apiTitles($c.'&f[marka_automobila]=audi&f[godina_od]=2010'));
        $this->assertSame(['Audi A6 Avant'], $this->apiTitles($c.'&f[kilometraza_do]=160.000'));
        $this->getJson('/api/v1/listings?'.$c.'&f[model_automobila]=nepostoji')->assertStatus(400);

        $page = $this->get('/oglasi/automobili/?f[marka_automobila]=audi')->assertOk();
        $page->assertSee('Audi A4 2009')->assertSee('Audi A6 Avant')->assertDontSee('Golf 4');
        // Only Audi's models are offered once Audi is chosen; without a make they come grouped by make.
        $page->assertSee('data-depends-on="marka_automobila"', false)->assertSee('>A4<', false)->assertDontSee('>Golf 4<', false);
        $this->get('/oglasi/automobili/')->assertOk()->assertSee('<optgroup label="Volkswagen">', false)->assertSee('Gorivo');
    }

    public function test_posting_form_offers_the_models_of_the_make(): void
    {
        $user = $this->seller;
        $listing = $this->listing(['category_id' => $this->limuzina->id, 'status' => ListingStatus::Draft, 'published_at' => null]);

        $page = $this->actingAs($user)->get('/postavi-oglas/'.$listing->id.'/detalji/')->assertOk();
        $page->assertSee('data-needs-parent', false)->assertSee('<optgroup label="Audi">', false)->assertSee('Kilometraža');

        $this->actingAs($user)->put('/postavi-oglas/'.$listing->id.'/detalji/', ['fields' => [
            'marka_automobila' => 'bmw', 'model_automobila' => 'audi-a4', 'godina' => '2009', 'stanje' => 'korisceno',
        ]])->assertSessionHasErrors(['fields.model_automobila']);

        $this->actingAs($user)->put('/postavi-oglas/'.$listing->id.'/detalji/', ['fields' => [
            'marka_automobila' => 'audi', 'model_automobila' => 'audi-a4', 'godina' => '2009', 'kilometraza' => '214.000', 'stanje' => 'korisceno',
        ]])->assertSessionHasNoErrors();

        $page = $this->actingAs($user)->get('/postavi-oglas/'.$listing->id.'/detalji/')->assertOk();
        $page->assertSee('value="audi-a4" data-parent="audi" selected', false)->assertDontSee('<optgroup label="BMW">', false);
    }
}
