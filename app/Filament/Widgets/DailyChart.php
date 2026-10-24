<?php

namespace App\Filament\Widgets;

use App\Services\Admin\AdminStats;
use Filament\Widgets\ChartWidget;

/** New listings and users, views, paid orders and revenue per day, last 30 days. */
class DailyChart extends ChartWidget
{
    protected ?string $heading = 'Poslednjih 30 dana';

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '280px';

    public ?string $filter = 'listings';

    protected function getFilters(): ?array
    {
        return array_filter([
            'listings' => 'Novi oglasi i korisnici',
            'views' => 'Pregledi oglasa',
            'orders' => auth()->user()?->isAdmin() ? 'Narudžbine i prihod' : null,
        ]);
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $daily = app(AdminStats::class)->daily(30);

        $filter = $this->filter === 'orders' && ! auth()->user()?->isAdmin() ? 'listings' : $this->filter;

        $datasets = match ($filter) {
            'views' => [['label' => 'Pregledi', 'data' => $daily['views'], 'borderColor' => '#2B7A64', 'backgroundColor' => 'rgba(43, 122, 100, 0.12)', 'fill' => true, 'tension' => 0.3]],
            'orders' => [
                ['label' => 'Plaćene narudžbine', 'data' => $daily['orders'], 'borderColor' => '#2B7A64', 'backgroundColor' => '#2B7A64', 'tension' => 0.3, 'yAxisID' => 'y'],
                ['label' => 'Prihod (din)', 'data' => $daily['revenue'], 'borderColor' => '#B8501A', 'backgroundColor' => '#B8501A', 'tension' => 0.3, 'yAxisID' => 'y1'],
            ],
            default => [
                ['label' => 'Novi oglasi', 'data' => $daily['listings'], 'borderColor' => '#2B7A64', 'backgroundColor' => '#2B7A64', 'tension' => 0.3],
                ['label' => 'Novi korisnici', 'data' => $daily['users'], 'borderColor' => '#B8501A', 'backgroundColor' => '#B8501A', 'tension' => 0.3],
            ],
        };

        return ['datasets' => $datasets, 'labels' => $daily['labels']];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'scales' => array_filter([
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'y1' => $this->filter === 'orders' ? ['beginAtZero' => true, 'position' => 'right', 'grid' => ['drawOnChartArea' => false]] : null,
            ]),
        ];
    }
}
