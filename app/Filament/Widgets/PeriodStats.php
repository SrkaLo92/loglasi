<?php

namespace App\Filament\Widgets;

use App\Services\Admin\AdminStats;
use Filament\Widgets\Widget;

/** Totals for today, the last 7 and the last 30 days. */
class PeriodStats extends Widget
{
    protected string $view = 'admin.widgets.period';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $stats = app(AdminStats::class);
        $periods = ['today' => 'Danas', '7d' => '7 dana', '30d' => '30 dana'];
        $totals = array_map(fn ($p) => $stats->totals($p), array_combine(array_keys($periods), array_keys($periods)));

        $rows = [
            'listings' => 'Novi oglasi',
            'users' => 'Novi korisnici',
            'firms' => 'Nove firme',
            'views' => 'Pregledi oglasa',
            'reveals' => 'Otkriveni telefoni',
            'revenue' => 'Prihod (plaćene narudžbine)',
        ];

        return compact('periods', 'totals', 'rows');
    }
}
