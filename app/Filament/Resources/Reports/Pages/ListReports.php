<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Enums\ReportStatus;
use App\Filament\Resources\Reports\ReportResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    public function getTabs(): array
    {
        $status = fn (ReportStatus $s) => fn (Builder $query) => $query->where('status', $s);

        return [
            'open' => Tab::make('Otvorene')
                ->modifyQueryUsing($status(ReportStatus::Open))
                ->badge(fn () => ReportResource::getNavigationBadge())
                ->badgeColor('danger'),
            'resolved' => Tab::make('Rešene')->modifyQueryUsing($status(ReportStatus::Resolved)),
            'dismissed' => Tab::make('Odbačene')->modifyQueryUsing($status(ReportStatus::Dismissed)),
            'all' => Tab::make('Sve'),
        ];
    }
}
