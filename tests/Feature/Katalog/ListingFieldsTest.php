<?php

namespace Tests\Feature\Katalog;

use App\Enums\FieldFilter;
use App\Enums\FieldType;
use App\Models\Category;
use App\Models\Field;
use App\Models\Listing;
use App\Services\Catalog;
use App\Services\ListingFields;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ListingFieldsTest extends TestCase
{
    private ListingFields $fields;

    private Category $vozila;

    private Category $automobili;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vozila = Category::create(['name' => 'Vozila', 'slug' => 'vozila']);
        $this->automobili = Category::create(['name' => 'Automobili', 'slug' => 'automobili', 'parent_id' => $this->vozila->id]);

        $make = fn (array $a) => Field::create($a);
        $stanje = $make(['key' => 'stanje', 'label' => 'Stanje', 'type' => FieldType::Select, 'filter' => FieldFilter::Multi,
            'options' => [['value' => 'novo', 'label' => 'Novo'], ['value' => 'polovno', 'label' => 'Polovno'], ['value' => 'osteceno', 'label' => 'Oštećeno']]]);
        $marka = $make(['key' => 'marka', 'label' => 'Marka', 'type' => FieldType::Select, 'filter' => FieldFilter::Select,
            'options' => [['value' => 'skoda', 'label' => 'Škoda'], ['value' => 'audi', 'label' => 'Audi']]]);
        $model = $make(['key' => 'model', 'label' => 'Model', 'type' => FieldType::Text, 'filter' => FieldFilter::None]);
        $godiste = $make(['key' => 'godiste', 'label' => 'Godište', 'type' => FieldType::Year, 'filter' => FieldFilter::Range]);
        $km = $make(['key' => 'kilometraza', 'label' => 'Kilometraža', 'type' => FieldType::Number, 'filter' => FieldFilter::Range, 'unit' => 'km']);
        $oprema = $make(['key' => 'oprema', 'label' => 'Oprema', 'type' => FieldType::Multiselect, 'filter' => FieldFilter::Multi,
            'options' => [['value' => 'klima', 'label' => 'Klima'], ['value' => 'abs', 'label' => 'ABS'], ['value' => 'navigacija', 'label' => 'Navigacija']]]);
        $zamena = $make(['key' => 'moguca_zamena', 'label' => 'Moguća zamena', 'type' => FieldType::Checkbox, 'filter' => FieldFilter::Select]);

        $this->vozila->fields()->attach([$stanje->id => ['sort' => 1, 'required' => false]]);
        $this->automobili->fields()->attach([
            $marka->id => ['sort' => 1, 'required' => true],
            $model->id => ['sort' => 2, 'required' => false],
            $godiste->id => ['sort' => 3, 'required' => true],
            $km->id => ['sort' => 4, 'required' => false],
            $oprema->id => ['sort' => 5, 'required' => false],
            $zamena->id => ['sort' => 6, 'required' => false],
        ]);
        app(Catalog::class)->flush();
        $this->fields = app(ListingFields::class);
    }

    private function errors(array $input): array
    {
        try {
            $this->fields->validate($this->automobili, $input);
        } catch (ValidationException $e) {
            return array_map(fn ($m) => $m[0], $e->errors());
        }

        return [];
    }

    public function test_rules_per_type(): void
    {
        $rules = $this->fields->rules($this->automobili);

        $this->assertSame(['nullable', 'array'], $rules['fields']);
        $this->assertContains('required', $rules['fields.marka']);
        $this->assertContains('nullable', $rules['fields.model']);
        $this->assertContains('max:255', $rules['fields.model']);
        $this->assertContains('integer', $rules['fields.godiste']);
        $this->assertContains('numeric', $rules['fields.kilometraza']);
        $this->assertContains('array', $rules['fields.oprema']);
        $this->assertArrayHasKey('fields.oprema.*', $rules);
        $this->assertContains('boolean', $rules['fields.moguca_zamena']);
        $this->assertArrayHasKey('fields.stanje', $rules); // nasleđeno od roditelja
        $this->assertArrayHasKey('podaci.marka', $this->fields->rules($this->automobili, 'podaci'));
    }

    public function test_validation_messages_are_serbian(): void
    {
        $errors = $this->errors([
            'marka' => 'trabant',
            'godiste' => 1850,
            'kilometraza' => 'mnogo',
            'oprema' => ['klima', 'turbo'],
            'moguca_zamena' => 'mozda',
            'model' => str_repeat('a', 256),
        ]);

        $this->assertSame('Izabrana vrednost za „Marka” ne postoji.', $errors['fields.marka']);
        $this->assertSame('Godina u polju „Godište” nije ispravna.', $errors['fields.godiste']);
        $this->assertSame('Polje „Kilometraža” mora biti broj.', $errors['fields.kilometraza']);
        $this->assertSame('Izabrana vrednost za „Oprema” ne postoji.', $errors['fields.oprema.1']);
        $this->assertSame('Polje „Moguća zamena” može biti samo da ili ne.', $errors['fields.moguca_zamena']);
        $this->assertSame('Polje „Model” može imati najviše 255 znakova.', $errors['fields.model']);

        $missing = $this->errors([]);
        $this->assertSame('Polje „Marka” je obavezno.', $missing['fields.marka']);
        $this->assertSame('Polje „Godište” je obavezno.', $missing['fields.godiste']);
        $this->assertArrayNotHasKey('fields.model', $missing);

        $this->assertSame('Vrednost polja „Kilometraža” ne može biti negativna.', $this->errors(['marka' => 'audi', 'godiste' => 2010, 'kilometraza' => -5])['fields.kilometraza']);
        $this->assertSame('Godina u polju „Godište” nije ispravna.', $this->errors(['marka' => 'audi', 'godiste' => (int) date('Y') + 2])['fields.godiste']);
    }

    public function test_validate_normalizes_and_drops_unknown_keys(): void
    {
        $values = $this->fields->validate($this->automobili, [
            'marka' => 'skoda',
            'model' => '  Octavia  ',
            'godiste' => '2014',
            'kilometraza' => '180000.00',
            'oprema' => ['klima', 'abs'],
            'moguca_zamena' => '1',
            'stanje' => 'polovno',
            'nepoznato' => 'x',
        ]);

        $this->assertSame([
            'stanje' => 'polovno',
            'marka' => 'skoda',
            'model' => 'Octavia',
            'godiste' => '2014',
            'kilometraza' => '180000',
            'oprema' => ['klima', 'abs'],
            'moguca_zamena' => true,
        ], $values);

        $this->assertArrayNotHasKey('moguca_zamena', $this->fields->validate($this->automobili, ['marka' => 'audi', 'godiste' => 2001, 'moguca_zamena' => false]));
    }

    public function test_save_and_display_values(): void
    {
        $listing = Listing::factory()->create(['category_id' => $this->automobili->id]);

        $this->fields->save($listing, $this->fields->validate($this->automobili, [
            'marka' => 'skoda', 'model' => 'Octavia', 'godiste' => 2014, 'kilometraza' => 180000,
            'oprema' => ['klima', 'navigacija'], 'moguca_zamena' => true, 'stanje' => 'polovno',
        ]));

        $this->assertDatabaseCount('listing_field_values', 8);
        $this->assertDatabaseHas('listing_field_values', ['listing_id' => $listing->id, 'value' => '180000', 'value_num' => 180000]);

        $this->assertSame([
            ['key' => 'stanje', 'label' => 'Stanje', 'value' => 'polovno', 'display' => 'Polovno'],
            ['key' => 'marka', 'label' => 'Marka', 'value' => 'skoda', 'display' => 'Škoda'],
            ['key' => 'model', 'label' => 'Model', 'value' => 'Octavia', 'display' => 'Octavia'],
            ['key' => 'godiste', 'label' => 'Godište', 'value' => '2014', 'display' => '2014.'],
            ['key' => 'kilometraza', 'label' => 'Kilometraža', 'value' => '180000', 'display' => '180.000 km'],
            ['key' => 'oprema', 'label' => 'Oprema', 'value' => 'klima,navigacija', 'display' => 'Klima, Navigacija'],
            ['key' => 'moguca_zamena', 'label' => 'Moguća zamena', 'value' => '1', 'display' => 'Da'],
        ], $this->fields->values($listing->fresh()));

        $this->assertSame('polovno', $this->fields->condition($listing->fresh()));

        // Ponovni upis zamenjuje stare vrednosti.
        $this->fields->save($listing, ['marka' => 'audi', 'godiste' => '2001', 'stanje' => 'novo']);
        $this->assertDatabaseCount('listing_field_values', 3);
        $this->assertSame('novo', $this->fields->condition($listing->fresh()));
    }

    public function test_decimal_numbers_and_missing_condition(): void
    {
        $listing = Listing::factory()->create(['category_id' => $this->automobili->id]);
        $this->fields->save($listing, $this->fields->normalize($this->automobili, ['kilometraza' => '1234.5', 'marka' => 'audi']));

        $values = collect($this->fields->values($listing->fresh()))->keyBy('key');
        $this->assertSame('1234.5', $values['kilometraza']['value']);
        $this->assertSame('1.234,5 km', $values['kilometraza']['display']);
        $this->assertNull($this->fields->condition($listing->fresh()));
    }

    public function test_values_of_fields_outside_category_are_not_shown(): void
    {
        $listing = Listing::factory()->create(['category_id' => $this->automobili->id]);
        $this->fields->save($listing, ['marka' => 'audi', 'stanje' => 'novo']);

        $listing->update(['category_id' => $this->vozila->id]);

        $this->assertSame(['stanje'], array_column($this->fields->values($listing->fresh()), 'key'));
    }

    public function test_filter_definitions(): void
    {
        $filters = collect($this->fields->filters($this->automobili))->keyBy('key');

        $this->assertFalse($filters->has('model'));
        $this->assertSame('exact', $filters['marka']['filter']);
        $this->assertSame('multi', $filters['stanje']['filter']);
        $this->assertSame(['godiste_od', 'godiste_do'], $filters['godiste']['params']);
        $this->assertSame(['marka'], $filters['marka']['params']);
        $this->assertSame('km', $filters['kilometraza']['unit']);
    }

    public function test_parse_filters_errors(): void
    {
        $cases = [
            'Filteri polja se šalju kao f[ključ]=vrednost.' => 'tekst',
            'Nepoznat filter: model.' => ['model' => 'Octavia'],
            'Nepoznat filter: boja.' => ['boja' => 'crvena'],
            'Za „Marka” je dozvoljena jedna vrednost.' => ['marka' => 'skoda,audi'],
            'Neispravna vrednost za „Stanje”.' => ['stanje' => 'lose'],
            'Za „Godište” se šalju brojevi kao _od i _do.' => ['godiste' => '2010'],
            'Za „Kilometraža” se šalju brojevi kao _od i _do.' => ['kilometraza_od' => 'malo'],
            'Za „Godište” vrednost „od” ne može biti veća od „do”.' => ['godiste_od' => '2015', 'godiste_do' => '2010'],
        ];

        foreach ($cases as $message => $input) {
            try {
                $this->fields->parseFilters($this->automobili, $input);
                $this->fail('Očekivana greška: '.$message);
            } catch (ValidationException $e) {
                $this->assertSame($message, $e->errors()['f'][0]);
            }
        }

        $this->assertSame([], $this->fields->parseFilters($this->automobili, null));
        $this->expectException(ValidationException::class);
        $this->fields->parseFilters(null, ['stanje' => 'novo']);
    }

    public function test_filters_apply_to_listing_query(): void
    {
        $make = function (array $values) {
            $listing = Listing::factory()->create(['category_id' => $this->automobili->id]);
            $this->fields->save($listing, $this->fields->normalize($this->automobili, $values));

            return $listing->id;
        };

        $a = $make(['marka' => 'skoda', 'godiste' => 2014, 'kilometraza' => 180000, 'stanje' => 'polovno', 'oprema' => ['klima'], 'moguca_zamena' => true]);
        $b = $make(['marka' => 'audi', 'godiste' => 2008, 'kilometraza' => 250000, 'stanje' => 'osteceno', 'oprema' => ['abs', 'klima']]);
        $c = $make(['marka' => 'skoda', 'godiste' => 2020, 'kilometraza' => 40000, 'stanje' => 'novo']);

        $find = function (array $f) {
            $conditions = $this->fields->parseFilters($this->automobili, $f);

            return $this->fields->apply(Listing::query(), $conditions)->orderBy('id')->pluck('id')->all();
        };

        $this->assertSame([$a, $c], $find(['marka' => 'skoda']));
        $this->assertSame([$a, $b], $find(['stanje' => 'polovno,osteceno']));
        $this->assertSame([$a, $c], $find(['godiste_od' => '2010']));
        $this->assertSame([$a], $find(['godiste_od' => '2010', 'godiste_do' => '2015']));
        $this->assertSame([$b], $find(['kilometraza_od' => '200000']));
        $this->assertSame([$a, $b], $find(['oprema' => 'klima']));
        $this->assertSame([$a], $find(['moguca_zamena' => '1']));
        $this->assertSame([$c], $find(['marka' => 'skoda', 'kilometraza_do' => '100000']));
        $this->assertSame([$a, $b, $c], $find([]));
    }
}
