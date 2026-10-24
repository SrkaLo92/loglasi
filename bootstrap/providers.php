<?php

use App\Providers\AppServiceProvider;
use App\Providers\FirmsServiceProvider;
use App\Providers\KatalogServiceProvider;
use App\Providers\ListingServiceProvider;
use App\Providers\MessagesServiceProvider;
use App\Providers\NaloziServiceProvider;
use App\Providers\PostingServiceProvider;
use App\Providers\SearchServiceProvider;
use App\Providers\SeoServiceProvider;

return [
    AppServiceProvider::class,
    KatalogServiceProvider::class,
    ListingServiceProvider::class,
    MessagesServiceProvider::class,
    NaloziServiceProvider::class,
    PostingServiceProvider::class,
    SearchServiceProvider::class,
    SeoServiceProvider::class,
    FirmsServiceProvider::class,
];
