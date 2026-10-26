<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Listing;
use App\Services\Catalog;
use Database\Seeders\CatalogSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogServiceTest extends TestCase
{
    private Catalog $catalog;

    private Category $nekretnine;

    private Category $stanovi;

    private Category $garsonjere;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nekretnine = Category::create(['name' => 'Nekretnine', 'slug' => 'nekretnine']);
        $this->stanovi = Category::create(['name' => 'Stanovi', 'slug' => 'stanovi', 'parent_id' => $this->nekretnine->id]);
        $this->garsonjere = Category::create(['name' => 'Garsonjere', 'slug' => 'garsonjere', 'parent_id' => $this->stanovi->id]);
        Category::create(['name' => 'Kuće', 'slug' => 'kuce', 'parent_id' => $this->nekretnine->id]);
        $this->catalog = app(Catalog::class);
    }

    public function test_breadcrumbs_and_ancestors(): void
    {
        $this->assertSame(['Nekretnine', 'Stanovi', 'Garsonjere'], $this->catalog->breadcrumbs($this->garsonjere)->pluck('name')->all());
        $this->assertSame(['nekretnine', 'stanovi'], $this->catalog->ancestors($this->garsonjere->id)->pluck('slug')->all());
        $this->assertSame([], $this->catalog->ancestors($this->nekretnine)->all());
    }

    public function test_descendant_ids(): void
    {
        $ids = $this->catalog->descendantIds($this->nekretnine);

        $this->assertCount(4, $ids);
        $this->assertSame($this->nekretnine->id, $ids[0]);
        $this->assertSame([$this->garsonjere->id], $this->catalog->descendantIds($this->stanovi, false));
        $this->assertSame([$this->garsonjere->id], $this->catalog->descendantIds($this->garsonjere));
    }

    public function test_category_by_slug_and_path(): void
    {
        $this->assertSame($this->stanovi->id, $this->catalog->categoryBySlug('stanovi')->id);
        $this->assertSame($this->garsonjere->id, $this->catalog->categoryByPath('garsonjere')->id);
        $this->assertSame($this->garsonjere->id, $this->catalog->categoryByPath('/stanovi/garsonjere/')->id);
        $this->assertSame($this->garsonjere->id, $this->catalog->categoryByPath('nekretnine/stanovi/garsonjere')->id);
        $this->assertNull($this->catalog->categoryByPath('kuce/garsonjere'));
        $this->assertNull($this->catalog->categoryByPath('nema'));
        $this->assertNull($this->catalog->categoryByPath(''));

        $this->stanovi->update(['is_active' => false]);
        $this->assertNull($this->catalog->categoryByPath('garsonjere'));
    }

    public function test_cycle_in_tree_does_not_hang(): void
    {
        DB::table('categories')->where('id', $this->nekretnine->id)->update(['parent_id' => $this->garsonjere->id]);
        $this->catalog->flush();

        $this->assertLessThanOrEqual(4, $this->catalog->breadcrumbs($this->garsonjere)->count());
        $this->assertCount(4, $this->catalog->descendantIds($this->stanovi));
        $this->assertFalse($this->catalog->isVisible($this->garsonjere));
        $this->assertSame([], $this->catalog->tree());
    }

    public function test_mass_update_needs_manual_flush(): void
    {
        Listing::factory()->create(['category_id' => $this->garsonjere->id]);
        $this->assertSame(1, $this->catalog->count($this->nekretnine));

        Listing::query()->update(['status' => 'expired']);
        $this->catalog->flushCounts();

        $this->assertSame(0, $this->catalog->count($this->nekretnine));
    }

    public function test_tree_queries_do_not_grow_with_categories(): void
    {
        $this->catalog->flush();
        DB::enableQueryLog();
        $this->catalog->tree();
        $first = count(DB::getQueryLog());

        $this->catalog->tree();
        $this->assertLessThanOrEqual(4, $first);
        $this->assertSame($first, count(DB::getQueryLog()));
    }

    public function test_seeder_builds_catalog_and_is_repeatable(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->seed(CatalogSeeder::class);

        foreach (['automobili', 'nekretnine', 'mobilni-telefoni', 'poslovi', 'usluge', 'besplatne-stvari', 'gradjevinarstvo'] as $slug) {
            $this->assertNotNull($this->catalog->categoryBySlug($slug), $slug);
        }
        $this->assertNotNull($this->catalog->locationBySlug('banja-koviljaca'));
        $this->assertNotNull($this->catalog->locationBySlug('trsic'));

        $keys = $this->catalog->fieldsFor($this->catalog->categoryBySlug('karavan'))->pluck('key')->all();
        $this->assertSame(['marka', 'model', 'godiste', 'kilometraza', 'gorivo'], array_slice($keys, 0, 5));
        $this->assertContains('stanje', $keys);
        $this->assertSame(['kvadratura', 'grejanje', 'vrsta_oglasa', 'sobe', 'sprat'], $this->catalog->fieldsFor($this->catalog->categoryBySlug('stanovi'))->pluck('key')->all());
    }
}
