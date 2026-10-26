<?php

namespace Tests\Feature\Catalog;

use App\Enums\FieldFilter;
use App\Enums\FieldType;
use App\Models\Category;
use App\Models\Field;
use App\Services\Catalog;
use Tests\TestCase;

class FieldsApiTest extends TestCase
{
    private Category $vozila;

    private Category $automobili;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vozila = Category::create(['name' => 'Vozila', 'slug' => 'vozila']);
        $this->automobili = Category::create(['name' => 'Automobili', 'slug' => 'automobili', 'parent_id' => $this->vozila->id]);

        $stanje = Field::create(['key' => 'stanje', 'label' => 'Stanje', 'type' => FieldType::Select, 'filter' => FieldFilter::Multi,
            'options' => [['value' => 'novo', 'label' => 'Novo'], ['value' => 'polovno', 'label' => 'Polovno']]]);
        $zamena = Field::create(['key' => 'moguca_zamena', 'label' => 'Moguća zamena', 'type' => FieldType::Checkbox, 'filter' => FieldFilter::Select]);
        $marka = Field::create(['key' => 'marka', 'label' => 'Marka', 'type' => FieldType::Select, 'filter' => FieldFilter::Select,
            'options' => [['value' => 'skoda', 'label' => 'Škoda']]]);
        $km = Field::create(['key' => 'kilometraza', 'label' => 'Kilometraža', 'type' => FieldType::Number, 'filter' => FieldFilter::Range, 'unit' => 'km']);
        $godiste = Field::create(['key' => 'godiste', 'label' => 'Godište', 'type' => FieldType::Year, 'filter' => FieldFilter::Range]);

        $this->vozila->fields()->attach([$stanje->id => ['sort' => 1], $zamena->id => ['sort' => 2]]);
        $this->automobili->fields()->attach([
            $km->id => ['sort' => 2, 'required' => false],
            $marka->id => ['sort' => 1, 'required' => true],
            $godiste->id => ['sort' => 3, 'required' => false],
            $zamena->id => ['sort' => 9, 'required' => true],
        ]);
        app(Catalog::class)->flush();
    }

    public function test_fields_are_inherited_and_ordered(): void
    {
        $response = $this->getJson('/api/v1/fields?category='.$this->automobili->id)->assertOk();

        $this->assertSame(['stanje', 'moguca_zamena', 'marka', 'kilometraza', 'godiste'], array_column($response->json(), 'key'));
        $response->assertJsonPath('0', [
            'key' => 'stanje', 'label' => 'Stanje', 'type' => 'select', 'required' => false, 'unit' => null, 'depends_on' => null,
            'options' => [['value' => 'novo', 'label' => 'Novo'], ['value' => 'polovno', 'label' => 'Polovno']],
            'filter' => 'multi',
        ]);
        $response->assertJsonPath('1.required', true); // dete menja obaveznost nasleđenog polja
        $response->assertJsonPath('1.options', []);
        $response->assertJsonPath('2.filter', 'exact');
        $response->assertJsonPath('3.unit', 'km');
        $response->assertJsonPath('3.filter', 'range');
        $response->assertJsonPath('4.type', 'year');
        $this->assertStringContainsString('Moguća zamena', $response->getContent());
        $this->assertStringContainsString('max-age=300', $response->headers->get('Cache-Control'));
    }

    public function test_parent_gets_its_fields_then_the_subcategory_fields_for_filters(): void
    {
        $response = $this->getJson('/api/v1/fields?category='.$this->vozila->id)->assertOk();

        // Own fields first, then what the subcategories add, all optional (a parent is only filtered, never posted to).
        $this->assertSame(['stanje', 'moguca_zamena', 'marka', 'kilometraza', 'godiste'], array_column($response->json(), 'key'));
        $this->assertSame([false, false, false, false, false], array_column($response->json(), 'required'));
    }

    public function test_rest_style_route_returns_same_body(): void
    {
        $a = $this->get('/api/v1/fields?category='.$this->automobili->id);
        $b = $this->get('/api/v1/categories/'.$this->automobili->id.'/fields');

        $b->assertOk();
        $this->assertSame($a->getContent(), $b->getContent());
        $this->assertSame($a->headers->get('ETag'), $b->headers->get('ETag'));
    }

    public function test_etag_304(): void
    {
        $etag = $this->get('/api/v1/categories/'.$this->automobili->id.'/fields')->headers->get('ETag');

        $this->get('/api/v1/categories/'.$this->automobili->id.'/fields', ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_field_change_invalidates_cache(): void
    {
        $this->getJson('/api/v1/fields?category='.$this->automobili->id)->assertJsonPath('2.label', 'Marka');

        Field::where('key', 'marka')->first()->update(['label' => 'Proizvođač']);

        $this->getJson('/api/v1/fields?category='.$this->automobili->id)->assertJsonPath('2.label', 'Proizvođač');
    }

    public function test_missing_category(): void
    {
        $this->getJson('/api/v1/fields')
            ->assertStatus(400)
            ->assertExactJson(['code' => 'missing_param', 'message' => 'Nedostaje obavezan parametar.', 'fields' => ['category' => 'Obavezno polje.']])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_invalid_category(): void
    {
        foreach (['abc', '0', '-1', '1.5', '01'] as $value) {
            $this->getJson('/api/v1/fields?category='.$value)
                ->assertStatus(400)
                ->assertExactJson(['code' => 'invalid_param', 'message' => 'Neispravni parametri.', 'fields' => ['category' => 'Mora biti pozitivan ceo broj.']]);
        }

        $this->getJson('/api/v1/fields?category[]=1')->assertStatus(400)->assertJsonPath('code', 'invalid_param');
        $this->getJson('/api/v1/categories/0/fields')->assertStatus(400)->assertJsonPath('fields.id', 'Mora biti pozitivan ceo broj.');
        $this->getJson('/api/v1/categories/abc/fields')->assertNotFound()->assertJsonPath('message', 'Tražena adresa ne postoji.');
    }

    public function test_unknown_or_inactive_category_is_404(): void
    {
        $this->getJson('/api/v1/fields?category=99999')
            ->assertNotFound()
            ->assertExactJson(['code' => 'not_found', 'message' => 'Kategorija ne postoji.']);

        $this->vozila->update(['is_active' => false]);
        $this->getJson('/api/v1/categories/'.$this->automobili->id.'/fields')->assertNotFound();
    }
}
