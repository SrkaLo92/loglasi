<?php

namespace Tests;

use App\Support\Pages\Pricing;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Settings and the founding-firm count are kept in a static array for a few seconds; every test starts from its own database.
        Settings::flush();
        Pricing::forgetFounding();
    }

    /** In tests Laravel otherwise drops the trailing slash of the URL; on the site it is part of the canonical address. */
    protected function prepareUrlForRequest($uri)
    {
        $uri = (string) $uri;

        return preg_match('#^https?://#i', $uri) ? $uri : rtrim(config('app.url'), '/').'/'.ltrim($uri, '/');
    }
}
