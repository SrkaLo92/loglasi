<?php

namespace Tests\Feature\Seo;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class SeoTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Location mora biti tačno ova putanja na domenu zahteva (sa kosom crtom na kraju).
        TestResponse::macro('assertTarget', function (string $path) {
            /** @var TestResponse $this */
            return $this->assertHeader('Location', rtrim(config('app.url'), '/').$path);
        });
    }
}
