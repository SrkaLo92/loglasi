<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\Users\UserResource;
use App\Services\Admin\AdminStats;
use Filament\Widgets\Widget;

/** "Waiting for you": what needs a decision, with links to filtered lists. */
class QueueCounters extends Widget
{
    protected string $view = 'admin.widgets.queue';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $queue = app(AdminStats::class)->queue();

        return ['items' => [
            ['label' => 'Oglasi na čekanju', 'count' => $queue['listings'], 'url' => ListingResource::getUrl('index', ['tab' => 'pending'])],
            ['label' => 'Telefoni za proveru', 'count' => $queue['phones'], 'url' => UserResource::getUrl('index', ['tab' => 'phone'])],
            ['label' => 'Narudžbine čekaju uplatu', 'count' => $queue['orders'], 'url' => OrderResource::getUrl('index', ['tab' => 'pending'])],
            ['label' => 'Otvorene prijave', 'count' => $queue['reports'], 'url' => ReportResource::getUrl('index', ['tab' => 'open'])],
        ]];
    }
}
