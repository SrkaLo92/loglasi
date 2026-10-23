<?php

use App\Providers\AppServiceProvider;
use App\Providers\KatalogServiceProvider;
use App\Providers\NaloziServiceProvider;
use App\Providers\PaymentsServiceProvider;
use App\Providers\SeoServiceProvider;

return [
    AppServiceProvider::class,
    KatalogServiceProvider::class,
    NaloziServiceProvider::class,
    PaymentsServiceProvider::class,
    SeoServiceProvider::class,
];
