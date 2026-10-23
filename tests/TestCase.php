<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Laravel u testu inače skida kosu crtu na kraju URL-a; na sajtu je ona deo kanonske adrese. */
    protected function prepareUrlForRequest($uri)
    {
        $uri = (string) $uri;

        return preg_match('#^https?://#i', $uri) ? $uri : rtrim(config('app.url'), '/').'/'.ltrim($uri, '/');
    }
}
