<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Users\UserResource;
use App\Services\Admin\AdminStats;
use Filament\Widgets\Widget;

/** "Na sajtu": how many listings there are now (per status), users and firms. */
class ListingTotals extends Widget
{
    protected string $view = 'admin.widgets.queue';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $c = app(AdminStats::class)->counts();
        $listings = fn (string $tab) => ListingResource::getUrl('index', ['tab' => $tab]);

        return ['heading' => 'Na sajtu', 'hot' => false, 'items' => [
            ['label' => 'Aktivnih oglasa', 'count' => $c['published'], 'url' => $listings('published')],
            ['label' => 'Na čekanju', 'count' => $c['pending'], 'url' => $listings('pending')],
            ['label' => 'Isteklih', 'count' => $c['expired'], 'url' => $listings('expired')],
            ['label' => 'Odbijenih', 'count' => $c['rejected'], 'url' => $listings('rejected')],
            ['label' => 'Svih oglasa', 'count' => $c['all'], 'url' => $listings('all')],
            ['label' => 'Korisnika', 'count' => $c['users'], 'url' => UserResource::getUrl('index')],
        ]];
    }
}
