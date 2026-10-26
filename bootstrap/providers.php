<?php

use App\Providers\AccountsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\CatalogServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FirmsServiceProvider;
use App\Providers\ListingServiceProvider;
use App\Providers\MessagesServiceProvider;
use App\Providers\NotificationsServiceProvider;
use App\Providers\PaymentsServiceProvider;
use App\Providers\PostingServiceProvider;
use App\Providers\SearchServiceProvider;
use App\Providers\SeoServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CatalogServiceProvider::class,
    ListingServiceProvider::class,
    MessagesServiceProvider::class,
    AccountsServiceProvider::class,
    NotificationsServiceProvider::class,
    PaymentsServiceProvider::class,
    PostingServiceProvider::class,
    SearchServiceProvider::class,
    SeoServiceProvider::class,
    FirmsServiceProvider::class,
];
