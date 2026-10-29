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

        // Same meaning as the "Na sajtu" tiles (AdminStats::counts).
        $pastExpiry = fn (Builder $q) => $q->where('status', ListingStatus::Published)->where('expires_at', '<=', now());

        return [
            'all' => Tab::make('Svi')->modifyQueryUsing(fn (Builder $query) => $query->where('status', '!=', ListingStatus::Draft))->badge($counts['all']),
            'pending' => Tab::make('Na čekanju')
                ->modifyQueryUsing($status(ListingStatus::Pending))
                ->badge($counts['pending'] ?: null)
                ->badgeColor('warning'),
            'published' => Tab::make('Aktivni')->modifyQueryUsing(fn (Builder $query) => $query->active())->badge($counts['published']),
            'rejected' => Tab::make('Odbijeni')->modifyQueryUsing($status(ListingStatus::Rejected))->badge($counts['rejected']),
            'expired' => Tab::make('Istekli')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('status', ListingStatus::Expired)->orWhere($pastExpiry)))
                ->badge($counts['expired']),
            'sold' => Tab::make('Prodati')->modifyQueryUsing($status(ListingStatus::Sold))->badge($counts['sold']),
            'drafts' => Tab::make('Nezavršeni')->modifyQueryUsing($status(ListingStatus::Draft))->badge($counts['drafts'])->badgeColor('gray'),
            'reported' => Tab::make('Prijavljeni')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('reports', fn ($r) => $r->where('status', ReportStatus::Open))),
            'featured' => Tab::make('Istaknuti')->modifyQueryUsing(fn (Builder $query) => $query->where('featured_until', '>', now())),
        ];
    }
}
