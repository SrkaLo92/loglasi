<?php

use App\Providers\AppServiceProvider;
use App\Providers\KatalogServiceProvider;
use App\Providers\NaloziServiceProvider;
use App\Providers\SearchServiceProvider;
use App\Providers\SeoServiceProvider;

return [
    AppServiceProvider::class,
    KatalogServiceProvider::class,
    NaloziServiceProvider::class,
    SearchServiceProvider::class,
    SeoServiceProvider::class,
];
