<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** In tests Laravel otherwise drops the trailing slash of the URL; on the site it is part of the canonical address. */
    protected function prepareUrlForRequest($uri)
    {
        $uri = (string) $uri;

        return preg_match('#^https?://#i', $uri) ? $uri : rtrim(config('app.url'), '/').'/'.ltrim($uri, '/');
    }
}
