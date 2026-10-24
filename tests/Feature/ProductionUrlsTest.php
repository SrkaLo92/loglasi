<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ProductionUrlsTest extends TestCase
{
    public function test_production_builds_urls_on_app_url_whatever_the_request_host(): void
    {
        config(['app.url' => 'https://loglasi.rs']);
        $this->app['env'] = 'production';
        (new AppServiceProvider($this->app))->boot();

        $this->get('http://192.168.0.31:8000/kontakt/');

        $this->assertSame('https://loglasi.rs/oglasi', URL::to('oglasi'));
    }
}
