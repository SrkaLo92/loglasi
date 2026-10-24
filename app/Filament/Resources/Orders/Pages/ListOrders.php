<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Support\Payments\PaymentMode;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /** While payments are in test mode the page says so in a banner. */
    public function getSubheading(): string|Htmlable|null
    {
        if (! PaymentMode::testMode()) {
            return null;
        }

        return new HtmlString('<span class="lg-test-mode" role="status"><strong>TEST REŽIM</strong> Plaćanja su u test režimu: narudžbine nisu prave uplate. Isključuje se u Podešavanja › Plaćanja.</span>');
    }

    public function getTabs(): array
    {
        $status = fn (OrderStatus $s) => fn (Builder $query) => $query->where('status', $s);

        return [
            'pending' => Tab::make('Čeka uplatu')
                ->modifyQueryUsing($status(OrderStatus::Pending))
                ->badge(fn () => OrderResource::getNavigationBadge())
                ->badgeColor('warning'),
            'paid' => Tab::make('Plaćeno, nije uključeno')->modifyQueryUsing($status(OrderStatus::Paid)),
            'activated' => Tab::make('Aktivirano')->modifyQueryUsing($status(OrderStatus::Activated)),
            'refunded' => Tab::make('Refundirano')->modifyQueryUsing($status(OrderStatus::Refunded)),
            'cancelled' => Tab::make('Otkazano')->modifyQueryUsing($status(OrderStatus::Cancelled)),
            'all' => Tab::make('Sve'),
        ];
    }
}
