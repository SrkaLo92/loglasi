<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Orders\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Narudžbine';

    protected static ?string $modelLabel = 'narudžbina';

    protected static ?string $pluralModelLabel = 'narudžbine';

    public function isReadOnly(): bool
    {
        return true;
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['product', 'listing']))
            ->defaultSort('created_at', 'desc')
            ->columns(OrderResource::columns(withUser: false))
            ->recordActions([
                OrderActions::markPaid(),
                OrderActions::cancel(),
            ]);
    }
}
