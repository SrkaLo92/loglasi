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

    /** Laravel inače skida kosu crtu na kraju URL-a u testu; ovde je bitna. */
    protected function prepareUrlForRequest($uri)
    {
        $uri = (string) $uri;

        return preg_match('#^https?://#i', $uri) ? $uri : rtrim(config('app.url'), '/').'/'.ltrim($uri, '/');
    }
}
