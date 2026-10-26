<?php

namespace Tests\Feature\Seo;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class SeoTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Location must be exactly this path on the request's domain (with the trailing slash).
        TestResponse::macro('assertTarget', function (string $path) {
            /** @var TestResponse $this */
            return $this->assertHeader('Location', rtrim(config('app.url'), '/').$path);
        });
    }
}
