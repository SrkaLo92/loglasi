<?php

namespace App\Filament\Widgets;

use App\Services\Admin\AdminStats;
use Filament\Widgets\Widget;

/** Main categories by number of active listings. */
class TopCategories extends Widget
{
    protected string $view = 'admin.widgets.top-categories';

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected function getViewData(): array
    {
        $rows = app(AdminStats::class)->topCategories(8);
        $max = max(1, ...array_column($rows, 'count') ?: [1]);

        return compact('rows', 'max');
    }
}
