<?php

namespace Tests\Feature\Search;

use App\Enums\Role;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ProbaPodaciCommandTest extends SearchTestCase
{
    public function test_replaces_real_users_and_listings_with_invented_ones_on_the_test_site(): void
    {
        config(['app.url' => 'https://loglasi-custom.alphasoft.dev']);
        Storage::fake('public');
        Storage::disk('public')->put('oglasi/1/slika.webp', UploadedFile::fake()->image('a.jpg')->getContent());

        $admin = User::factory()->create(['role' => Role::Admin]);
        $real = User::factory()->create(['email' => 'pravi@gmail.com']);
        Listing::factory()->for($real)->create();

        $this->artisan('proba:podaci', ['--sajt' => 'loglasi-custom.alphasoft.dev', '--oglasa' => 20, '--bez-slika' => true])->assertSuccessful();

        $this->assertModelExists($admin);
        $this->assertModelMissing($real);
        $this->assertSame(0, Listing::query()->whereNotIn('user_id', User::query()->where('email', 'like', '%@primer.loglasi.test')->select('id'))->count());
        $this->assertSame(20, Listing::count());
        Storage::disk('public')->assertMissing('oglasi/1/slika.webp');
    }

    public function test_refuses_on_the_live_site_and_without_the_host_check(): void
    {
        $real = User::factory()->create();

        config(['app.url' => 'https://loglasi.rs']);
        $this->artisan('proba:podaci', ['--sajt' => 'loglasi.rs'])->assertFailed();

        config(['app.url' => 'https://loglasi-custom.alphasoft.dev']);
        $this->artisan('proba:podaci')->assertFailed();
        $this->artisan('proba:podaci', ['--sajt' => 'drugi.sajt'])->assertFailed();

        $this->assertModelExists($real);
    }

    public function test_gives_invented_listings_photos(): void
    {
        config(['app.url' => 'https://loglasi-custom.alphasoft.dev']);
        Storage::fake('public');
        Http::fake(['*' => Http::response(UploadedFile::fake()->image('p.jpg', 800, 600)->getContent(), 200, ['Content-Type' => 'image/jpeg'])]);
        $this->app->useStoragePath(sys_get_temp_dir().'/proba-'.uniqid());

        $this->artisan('proba:podaci', ['--sajt' => 'loglasi-custom.alphasoft.dev', '--oglasa' => 6, '--bez-brisanja' => true])->assertSuccessful();

        $this->assertSame(6, Listing::query()->whereHas('images')->count());
        $this->assertNotNull(Listing::query()->first()->images()->first()->variants);
    }
}
