<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\KatalogServiceProvider;
use App\Providers\NaloziServiceProvider;
use App\Providers\SeoServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    KatalogServiceProvider::class,
    NaloziServiceProvider::class,
    SeoServiceProvider::class,
];
