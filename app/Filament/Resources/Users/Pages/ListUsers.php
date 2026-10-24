<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Svi'),
            'phone' => Tab::make('Telefon za proveru')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('phone')->where('phone', '!=', '')->whereNull('phone_verified_at'))
                ->badge(fn () => UserResource::getNavigationBadge())
                ->badgeColor('warning'),
            'firms' => Tab::make('Firme')->modifyQueryUsing(fn (Builder $query) => $query->where('account_type', AccountType::Firm)),
            'staff' => Tab::make('Osoblje')->modifyQueryUsing(fn (Builder $query) => $query->whereIn('role', [Role::Admin, Role::Moderator])),
            'blocked' => Tab::make('Blokirani')->modifyQueryUsing(fn (Builder $query) => $query->where('status', UserStatus::Blocked)),
        ];
    }
}
