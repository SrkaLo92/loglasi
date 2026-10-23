<?php

namespace Tests\Feature\Notifications;

use App\Enums\FieldFilter;
use App\Enums\FieldType;
use App\Models\Category;
use App\Models\Field;
use App\Models\Listing;
use App\Models\ListingFieldValue;
use App\Models\Location;
use App\Services\Catalog;

/** Small catalog for the notification tests: Vozila > Automobili, Nekretnine, two places, two fields. */
trait BuildsCatalog
{
    protected Category $vozila;

    protected Category $automobili;

    protected Category $nekretnine;

    protected Location $loznica;

    protected Location $banja;

    protected Field $marka;

    protected Field $km;

    protected function buildCatalog(): void
    {
        $this->vozila = Category::create(['name' => 'Vozila', 'slug' => 'vozila']);
        $this->automobili = Category::create(['name' => 'Automobili', 'slug' => 'automobili', 'parent_id' => $this->vozila->id]);
        $this->nekretnine = Category::create(['name' => 'Nekretnine', 'slug' => 'nekretnine']);
        $this->loznica = Location::create(['name' => 'Loznica', 'slug' => 'loznica']);
        $this->banja = Location::create(['name' => 'Banja Koviljača', 'slug' => 'banja-koviljaca']);

        $this->marka = Field::create(['key' => 'marka', 'label' => 'Marka', 'type' => FieldType::Select, 'filter' => FieldFilter::Select,
            'options' => [['value' => 'skoda', 'label' => 'Škoda'], ['value' => 'vw', 'label' => 'Volkswagen']]]);
        $this->km = Field::create(['key' => 'kilometraza', 'label' => 'Kilometraža', 'type' => FieldType::Number, 'filter' => FieldFilter::Range, 'unit' => 'km']);
        $this->automobili->fields()->attach([$this->marka->id => ['sort' => 1], $this->km->id => ['sort' => 2]]);

        app(Catalog::class)->flush();
    }

    /** @param  array<string, mixed>  $attributes */
    protected function car(array $attributes = [], ?string $marka = null, ?int $km = null): Listing
    {
        $listing = Listing::factory()->create($attributes + [
            'category_id' => $this->automobili->id,
            'location_id' => $this->loznica->id,
        ]);
        if ($marka !== null) {
            ListingFieldValue::create(['listing_id' => $listing->id, 'field_id' => $this->marka->id, 'value' => $marka]);
        }
        if ($km !== null) {
            ListingFieldValue::create(['listing_id' => $listing->id, 'field_id' => $this->km->id, 'value' => (string) $km, 'value_num' => $km]);
        }

        return $listing;
    }
}
