<?php

namespace Tests\Feature\Pages;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_404_in_site_look_and_serbian(): void
    {
        $this->get('/ova-strana-ne-postoji/')
            ->assertNotFound()
            ->assertSee('Strana nije pronađena')
            ->assertSee('class="lg-header"', false)
            ->assertSee('class="lg-footer"', false)
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('Svi oglasi')
            ->assertDontSee('Not Found');
    }

    public function test_410_page(): void
    {
        Route::get('/test-uklonjeno', fn () => abort(410));

        $this->get('/test-uklonjeno/')
            ->assertStatus(410)
            ->assertSee('Ova strana više ne postoji')
            ->assertDontSee('Gone');
    }

    public function test_500_page_without_database_or_session(): void
    {
        config(['app.debug' => false]);
        Route::get('/test-greska', fn () => throw new \RuntimeException('test'));

        $this->get('/test-greska/')
            ->assertStatus(500)
            ->assertSee('Nešto nije u redu')
            ->assertSee('css/base.css', false)
            ->assertDontSee('Server Error');
    }

    public function test_503_page(): void
    {
        Route::get('/test-odrzavanje', fn () => abort(503));

        $this->get('/test-odrzavanje/')
            ->assertStatus(503)
            ->assertSee('Sajt se trenutno sređuje')
            ->assertDontSee('Service Unavailable');
    }

    public function test_419_and_429_pages_are_serbian(): void
    {
        Route::post('/test-csrf', fn () => throw new TokenMismatchException);
        Route::get('/test-limit', fn () => abort(429));

        $this->post('/test-csrf')->assertStatus(419)->assertSee('Strana je istekla')->assertDontSee('Page Expired');
        $this->get('/test-limit/')->assertStatus(429)->assertSee('Previše zahteva')->assertDontSee('Too Many Requests');
    }

    public function test_403_page(): void
    {
        Route::get('/test-zabranjeno', fn () => abort(403));

        $this->get('/test-zabranjeno/')->assertForbidden()->assertSee('Pristup nije dozvoljen');
    }
}
