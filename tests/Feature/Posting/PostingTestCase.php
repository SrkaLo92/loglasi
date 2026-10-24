<?php

namespace Tests\Feature\Posting;

use App\Enums\FieldFilter;
use App\Enums\FieldType;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Field;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use App\Services\Catalog;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

abstract class PostingTestCase extends TestCase
{
    protected Category $vehicles;

    protected Category $cars;

    protected Category $services;

    protected Location $loznica;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->vehicles = Category::create(['name' => 'Vozila', 'slug' => 'vozila']);
        $this->cars = Category::create(['name' => 'Automobili', 'slug' => 'automobili', 'parent_id' => $this->vehicles->id]);
        $this->services = Category::create(['name' => 'Usluge', 'slug' => 'usluge']);
        $this->loznica = Location::create(['name' => 'Loznica', 'slug' => 'loznica']);

        $condition = Field::create(['key' => 'stanje', 'label' => 'Stanje', 'type' => FieldType::Select, 'filter' => FieldFilter::Multi,
            'options' => [['value' => 'novo', 'label' => 'Novo'], ['value' => 'polovno', 'label' => 'Polovno']]]);
        $year = Field::create(['key' => 'godiste', 'label' => 'Godište', 'type' => FieldType::Year, 'filter' => FieldFilter::Range]);
        $this->cars->fields()->attach([$condition->id => ['sort' => 2, 'required' => false], $year->id => ['sort' => 1, 'required' => true]]);
        app(Catalog::class)->flush();

        Settings::set('moderation', 'none');
    }

    protected function user(array $attributes = []): User
    {
        return User::factory()->phoneVerified()->create($attributes + ['created_at' => now()->subMonth()]);
    }

    protected function image(string $name = 'stripes.jpg', ?string $as = null): UploadedFile
    {
        $path = base_path('tests/Fixtures/images/'.$name);
        $copy = sys_get_temp_dir().'/'.uniqid('img', true).'-'.$name;
        copy($path, $copy);

        return new UploadedFile($copy, $as ?? $name, null, null, true);
    }

    /** A complete draft in "Usluge" (no fields), ready to submit. */
    protected function draft(User $user, array $attributes = []): Listing
    {
        return Listing::factory()->for($user)->create($attributes + [
            'category_id' => $this->services->id,
            'location_id' => $this->loznica->id,
            'title' => 'Krečenje stanova i kuća',
            'slug' => 'draft-'.uniqid(),
            'description' => 'Krečim stanove i kuće, brzo i povoljno.',
            'status' => ListingStatus::Draft,
            'published_at' => null,
            'bumped_at' => null,
            'expires_at' => null,
        ]);
    }

    protected function published(User $user, array $attributes = []): Listing
    {
        return Listing::factory()->for($user)->create($attributes + [
            'category_id' => $this->services->id,
            'location_id' => $this->loznica->id,
        ]);
    }
}
