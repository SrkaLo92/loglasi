<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Services\Admin\AdminStats;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListListings extends ListRecords
{
    protected static string $resource = ListingResource::class;

    public function getTabs(): array
    {
        $status = fn (ListingStatus $s) => fn (Builder $query) => $query->where('status', $s);
        $counts = app(AdminStats::class)->counts();

        return [
            'all' => Tab::make('Svi')->badge($counts['all']),
            'pending' => Tab::make('Na čekanju')
                ->modifyQueryUsing($status(ListingStatus::Pending))
                ->badge(fn () => ListingResource::getNavigationBadge())
                ->badgeColor('warning'),
            'published' => Tab::make('Aktivni')->modifyQueryUsing($status(ListingStatus::Published))->badge($counts['published']),
            'rejected' => Tab::make('Odbijeni')->modifyQueryUsing($status(ListingStatus::Rejected))->badge($counts['rejected']),
            'expired' => Tab::make('Istekli')->modifyQueryUsing($status(ListingStatus::Expired))->badge($counts['expired']),
            'reported' => Tab::make('Prijavljeni')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('reports', fn ($r) => $r->where('status', ReportStatus::Open))),
            'featured' => Tab::make('Istaknuti')->modifyQueryUsing(fn (Builder $query) => $query->where('featured_until', '>', now())),
        ];
    }
}
