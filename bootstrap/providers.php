<?php

use App\Providers\AppServiceProvider;
use App\Providers\KatalogServiceProvider;
use App\Providers\MessagesServiceProvider;
use App\Providers\NaloziServiceProvider;
use App\Providers\SeoServiceProvider;

return [
    AppServiceProvider::class,
    KatalogServiceProvider::class,
    MessagesServiceProvider::class,
    NaloziServiceProvider::class,
    SeoServiceProvider::class,
];
