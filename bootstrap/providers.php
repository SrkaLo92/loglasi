<?php

use App\Providers\AppServiceProvider;
use App\Providers\KatalogServiceProvider;
use App\Providers\NaloziServiceProvider;
use App\Providers\NotificationsServiceProvider;
use App\Providers\SeoServiceProvider;

return [
    AppServiceProvider::class,
    KatalogServiceProvider::class,
    NaloziServiceProvider::class,
    NotificationsServiceProvider::class,
    SeoServiceProvider::class,
];
