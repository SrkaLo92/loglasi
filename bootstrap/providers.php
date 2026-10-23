<?php

use App\Providers\AppServiceProvider;
use App\Providers\FirmsServiceProvider;
use App\Providers\KatalogServiceProvider;
use App\Providers\NaloziServiceProvider;
use App\Providers\SeoServiceProvider;

return [
    AppServiceProvider::class,
    KatalogServiceProvider::class,
    NaloziServiceProvider::class,
    SeoServiceProvider::class,
    FirmsServiceProvider::class,
];
