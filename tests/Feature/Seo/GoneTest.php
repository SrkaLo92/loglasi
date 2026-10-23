<?php

namespace Tests\Feature\Seo;

use App\Models\GoneUrl;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\File;

class GoneTest extends SeoTestCase
{
    public function test_gone_url_returns_410_with_and_without_trailing_slash(): void
    {
        $gone = GoneUrl::create(['path' => '/oglas/spam-oglas/', 'note' => 'spam']);

        $this->get('/oglas/spam-oglas/')->assertStatus(410)->assertHeader('X-Robots-Tag', 'noindex');
        $this->get('/oglas/spam-oglas')->assertStatus(410);
        $this->get('/oglas/spam-oglas/?utm_source=x')->assertStatus(410);

        $this->assertSame(3, $gone->fresh()->hits);
    }

    public function test_gone_plain_text_without_view(): void
    {
        GoneUrl::create(['path' => '/korisnik/spam-nalog/']);

        $response = $this->get('/korisnik/spam-nalog/');
        $response->assertStatus(410);
        $this->assertStringContainsString('trajno uklonjena', $response->getContent());
    }

    public function test_gone_uses_site_410_view_when_it_exists(): void
    {
        $dir = resource_path('views/errors');
        $created = ! is_dir($dir);
        File::ensureDirectoryExists($dir);
        File::put($dir.'/410.blade.php', '@extends(\'layouts.app\') @section(\'content\')<h1>Oglas je uklonjen</h1>@endsection');

        try {
            GoneUrl::create(['path' => '/oglas/obrisan/']);

            $this->get('/oglas/obrisan/')
                ->assertStatus(410)
                ->assertSee('Oglas je uklonjen')
                ->assertSee('<meta name="robots" content="noindex, follow">', false);
        } finally {
            File::delete($dir.'/410.blade.php');

            if ($created) {
                File::deleteDirectory($dir);
            }
        }
    }

    public function test_soft_deleted_listing_returns_410_instead_of_404(): void
    {
        $listing = Listing::factory()->create(['slug' => 'prodat-golf']);
        $listing->delete();

        $this->get('/oglas/prodat-golf/')->assertStatus(410);
        $this->get('/oglas/nikad-nije-postojao/')->assertNotFound();
    }

    public function test_soft_deleted_user_returns_410(): void
    {
        $user = User::factory()->create(['slug' => 'spamer']);
        $user->delete();

        $this->get('/korisnik/spamer/')->assertStatus(410);
    }

    public function test_gone_cache_is_cleared_on_change(): void
    {
        $this->get('/oglas/kasnije-spam/')->assertNotFound();

        GoneUrl::create(['path' => '/oglas/kasnije-spam/']);

        $this->get('/oglas/kasnije-spam/')->assertStatus(410);
    }
}
