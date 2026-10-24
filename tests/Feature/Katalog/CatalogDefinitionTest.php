<?php

namespace Tests\Feature\Katalog;

use App\Enums\FieldFilter;
use App\Enums\FieldType;
use App\Models\Category;
use App\Models\Field;
use App\Models\Listing;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\Catalog;
use App\Services\ListingFields;
use App\Support\Catalog\CatalogDefinition;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogDefinitionTest extends TestCase
{
    private Category $limuzina;

    private Category $delovi;

    private Category $samsung;

    private Category $razno;

    private Listing $audi;

    private Listing $golf;

    protected function setUp(): void
    {
        parent::setUp();

        $automobili = Category::create(['name' => 'Automobili', 'slug' => 'automobili']);
        $this->limuzina = Category::create(['name' => 'Automobili | Limuzina', 'slug' => 'automobili-limuzina', 'parent_id' => $automobili->id]);
        $this->delovi = Category::create(['name' => 'Auto delovi', 'slug' => 'auto-delovi', 'parent_id' => $automobili->id]);
        $telefoni = Category::create(['name' => 'Mobilni telefoni', 'slug' => 'mobilni-telefoni']);
        $this->samsung = Category::create(['name' => 'Samsung', 'slug' => 'samsung', 'parent_id' => $telefoni->id]);
        $usluge = Category::create(['name' => 'Usluge', 'slug' => 'usluge']);
        $this->razno = Category::create(['name' => 'Usluge | Razno', 'slug' => 'usluge-razno', 'parent_id' => $usluge->id]);

        // As the WordPress import leaves them: Listivo slugs, no make on models, godina as a number.
        $marka = Field::create(['key' => 'marka_automobila', 'label' => 'Marka automobila', 'type' => FieldType::Select, 'filter' => FieldFilter::Select, 'wp_key' => 'listivo_945',
            'options' => [['value' => 'audi', 'label' => 'Audi'], ['value' => 'volkswagen', 'label' => 'Volkswagen']]]);
        $model = Field::create(['key' => 'model_automobila', 'label' => 'Model automobila', 'type' => FieldType::Select, 'filter' => FieldFilter::Select, 'wp_key' => 'listivo_946',
            'options' => [['value' => 'a4', 'label' => 'A4'], ['value' => 'golf-4', 'label' => 'Golf 4']]]);
        $godina = Field::create(['key' => 'godina', 'label' => 'Godina', 'type' => FieldType::Number, 'filter' => FieldFilter::Range]);
        $km = Field::create(['key' => 'kilometraza', 'label' => 'Kilometraža', 'type' => FieldType::Number, 'filter' => FieldFilter::Range]);
        $prilog = Field::create(['key' => 'prilog', 'label' => 'Prilog', 'type' => FieldType::Text, 'filter' => FieldFilter::None]);
        $this->delovi->fields()->attach([$godina->id => ['sort' => 1], $km->id => ['sort' => 2]]);
        $this->razno->fields()->attach([$prilog->id => ['sort' => 1]]);

        $this->audi = Listing::factory()->create(['category_id' => $this->limuzina->id]);
        $this->golf = Listing::factory()->create(['category_id' => $this->limuzina->id]);
        $rows = [
            [$this->audi->id, $marka->id, 'audi', null],
            [$this->audi->id, $model->id, 'a4', null],
            [$this->audi->id, $godina->id, '2009', 2009],
            [$this->audi->id, $km->id, '214.000', null],
            [$this->golf->id, $model->id, 'golf-4', null], // model without make
            [$this->golf->id, $godina->id, '30', 30], // not a year
        ];
        foreach ($rows as [$listing, $field, $value, $num]) {
            DB::table('listing_field_values')->insert(['listing_id' => $listing, 'field_id' => $field, 'value' => $value, 'value_num' => $num]);
        }

        SavedSearch::create(['user_id' => User::factory()->create()->id, 'query' => ['category' => $this->limuzina->id, 'f' => ['model_automobila' => 'a4,golf-4', 'godina_od' => '2005']]]);
        app(Catalog::class)->flush();
    }

    private function apply(bool $dry = false): array
    {
        return (new CatalogDefinition)->apply($dry)['counts'];
    }

    private function value(Listing $listing, string $key): ?string
    {
        return DB::table('listing_field_values as v')->join('fields as f', 'f.id', '=', 'v.field_id')
            ->where('v.listing_id', $listing->id)->where('f.key', $key)->value('v.value');
    }

    public function test_car_fields_follow_polovniautomobili(): void
    {
        $this->apply();
        $catalog = app(Catalog::class);

        $keys = $catalog->fieldsFor($this->limuzina)->pluck('key')->all();
        $this->assertSame(['marka_automobila', 'model_automobila', 'godina', 'kilometraza', 'gorivo', 'kubikaza', 'snaga', 'menjac'], array_slice($keys, 0, 8));
        $this->assertContains('stanje', $keys);
        $this->assertNotContains('preuzimanje', $keys);
        $this->assertTrue($catalog->fieldsFor($this->limuzina)->firstWhere('key', 'marka_automobila')->required);

        $godina = Field::where('key', 'godina')->first();
        $this->assertSame(FieldType::Year, $godina->type);
        $this->assertSame('Godište', $godina->label);
        $this->assertSame('km', Field::where('key', 'kilometraza')->value('unit'));

        $model = Field::where('key', 'model_automobila')->first();
        $this->assertSame('marka_automobila', $model->depends_on);
        $this->assertContains(['value' => 'audi-a4', 'label' => 'A4', 'parent' => 'audi'], $model->options);
        $this->assertGreaterThan(1500, count($model->options));
        $makes = array_column(Field::where('key', 'marka_automobila')->first()->options, 'label');
        $this->assertContains('Cupra', $makes);
        $this->assertSame('Ostalo', end($makes));

        // Parts ask which car they fit, not its year and mileage.
        $this->assertSame(['marka_automobila', 'model_automobila', 'stanje', 'preuzimanje', 'moguca_zamena'], $catalog->fieldsFor($this->delovi)->pluck('key')->all());
    }

    public function test_old_values_move_to_the_new_options(): void
    {
        $counts = $this->apply();

        $this->assertSame('audi-a4', $this->value($this->audi, 'model_automobila'));
        $this->assertSame('214000', $this->value($this->audi, 'kilometraza'));
        $this->assertSame('volkswagen', $this->value($this->golf, 'marka_automobila'), 'the make comes from the model');
        $this->assertSame('volkswagen-golf-4', $this->value($this->golf, 'model_automobila'));
        $this->assertNull($this->value($this->golf, 'godina'));
        $this->assertSame(1, $counts['marke dopunjene iz modela']);

        $values = app(ListingFields::class)->values($this->audi->fresh()->load('fieldValues'));
        $this->assertSame(['Audi', 'A4', '2009.', '214.000 km'], array_column($values, 'display'));

        $query = SavedSearch::first()->query;
        $this->assertSame('audi-a4,volkswagen-golf-4', $query['f']['model_automobila']);
        $this->assertSame('2005', $query['f']['godina_od']);
    }

    public function test_every_category_gets_its_kind_of_fields(): void
    {
        $this->apply();
        $catalog = app(Catalog::class);

        $this->assertSame(['memorija', 'stanje', 'preuzimanje', 'moguca_zamena'], $catalog->fieldsFor($this->samsung)->pluck('key')->all());
        $this->assertSame([], $catalog->fieldsFor($this->razno)->pluck('key')->all());
        $this->assertSame('Razno', $this->razno->fresh()->name);
        $this->assertSame('Limuzina', $this->limuzina->fresh()->name);
        $this->assertSame('Auto delovi', $this->delovi->fresh()->name);
    }

    public function test_running_again_changes_nothing(): void
    {
        $this->apply();
        $snapshot = [
            DB::table('fields')->orderBy('id')->get(['key', 'label', 'type', 'options', 'depends_on', 'sort'])->toArray(),
            DB::table('category_field')->orderBy('category_id')->orderBy('sort')->get()->toArray(),
            DB::table('listing_field_values')->orderBy('id')->get(['listing_id', 'field_id', 'value'])->toArray(),
            DB::table('categories')->orderBy('id')->pluck('name')->all(),
        ];

        $counts = $this->apply();

        $this->assertEquals($snapshot, [
            DB::table('fields')->orderBy('id')->get(['key', 'label', 'type', 'options', 'depends_on', 'sort'])->toArray(),
            DB::table('category_field')->orderBy('category_id')->orderBy('sort')->get()->toArray(),
            DB::table('listing_field_values')->orderBy('id')->get(['listing_id', 'field_id', 'value'])->toArray(),
            DB::table('categories')->orderBy('id')->pluck('name')->all(),
        ]);
        $this->assertSame(0, $counts['veze kategorija-polje promenjene']);
    }

    public function test_dry_run_changes_nothing_and_the_command_reports(): void
    {
        $this->artisan('katalog:uredi', ['--dry-run' => true])
            ->expectsOutputToContain('marke dopunjene iz modela')
            ->expectsOutputToContain('Probno')
            ->assertSuccessful();

        $this->assertSame('a4', $this->value($this->audi, 'model_automobila'));
        $this->assertSame('Automobili | Limuzina', $this->limuzina->fresh()->name);
        $this->assertNull(Field::where('key', 'gorivo')->first());

        $this->artisan('katalog:uredi')->expectsOutputToContain('Katalog je uređen.')->assertSuccessful();
        $this->assertSame('audi-a4', $this->value($this->audi, 'model_automobila'));
    }
}
