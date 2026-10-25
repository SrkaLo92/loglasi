<?php

namespace App\Filament\Resources\SellerRatings\Pages;

use App\Filament\Resources\SellerRatings\SellerRatingResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSellerRatings extends ListRecords
{
    protected static string $resource = SellerRatingResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Sve'),
            'reported' => Tab::make('Prijavljene')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('reported_at'))
                ->badge(fn () => SellerRatingResource::getNavigationBadge())
                ->badgeColor('danger'),
        ];
    }
}
