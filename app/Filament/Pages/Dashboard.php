<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DailyChart;
use App\Filament\Widgets\PeriodStats;
use App\Filament\Widgets\QueueCounters;
use App\Filament\Widgets\TopCategories;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Pregled';

    protected static ?string $navigationLabel = 'Pregled';

    public function getWidgets(): array
    {
        return [
            QueueCounters::class,
            PeriodStats::class,
            DailyChart::class,
            TopCategories::class,
        ];
    }

    public function getColumns(): int|array
    {
        return ['default' => 1, 'lg' => 2];
    }
}
