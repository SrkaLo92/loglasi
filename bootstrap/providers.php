<?php

use App\Providers\AppServiceProvider;
use App\Providers\KatalogServiceProvider;
use App\Providers\ListingServiceProvider;
use App\Providers\NaloziServiceProvider;
use App\Providers\SearchServiceProvider;
use App\Providers\SeoServiceProvider;

return [
    AppServiceProvider::class,
    KatalogServiceProvider::class,
    ListingServiceProvider::class,
    NaloziServiceProvider::class,
    SearchServiceProvider::class,
    SeoServiceProvider::class,
];
