<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\Options;
use App\Filament\Support\StaffAccess;
use App\Models\Order;
use App\Models\Product;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use UnitEnum;

/** Bank transfer orders. Read-only table; writes go through OrderFulfillment. */
class OrderResource extends Resource
{
    use StaffAccess;

    protected static bool $readOnly = true;

    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Prodaja';

    protected static ?string $modelLabel = 'narudžbina';

    protected static ?string $pluralModelLabel = 'narudžbine';

    protected static ?string $navigationLabel = 'Narudžbine i uplate';

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationBadge(): ?string
    {
        $count = Order::query()->where('status', OrderStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Čeka uplatu';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'product', 'listing']);
    }

    /** @return list<TextColumn> */
    public static function columns(bool $withUser = true): array
    {
        return array_values(array_filter([
            TextColumn::make('reference')->label('Poziv na broj')->searchable()->copyable()->weight('bold'),
            $withUser ? TextColumn::make('user.name')
                ->label('Korisnik')
                ->searchable()
                ->url(fn (Order $record) => $record->user ? UserResource::getUrl('view', ['record' => $record->user]) : null) : null,
            TextColumn::make('product.name')->label('Usluga'),
            TextColumn::make('listing.title')
                ->label('Oglas')
                ->limit(40)
                ->placeholder('—')
                ->url(fn (Order $record) => $record->listing ? ListingResource::getUrl('edit', ['record' => $record->listing]) : null),
            TextColumn::make('amount_rsd')
                ->label('Iznos')
                ->formatStateUsing(fn ($state) => number_format((int) $state, 0, ',', '.').' din')
                ->sortable(),
            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                ->color(fn (OrderStatus $state) => match ($state) {
                    OrderStatus::Pending => 'warning',
                    OrderStatus::Paid => 'success',
                    OrderStatus::Cancelled => 'gray',
                    default => 'info',
                }),
            TextColumn::make('created_at')->label('Naručeno')->dateTime('j. n. Y. H:i')->sortable(),
            TextColumn::make('paid_at')->label('Plaćeno')->dateTime('j. n. Y. H:i')->placeholder('—')->sortable(),
        ]));
    }

    /** @var array<string, bool> */
    private static array $columnCache = [];

    /** Columns added by the payments module are shown only once they exist. */
    public static function hasColumn(string $column): bool
    {
        return self::$columnCache[$column] ??= Schema::hasColumn('orders', $column);
    }

    /** @return list<TextColumn> */
    public static function paymentColumns(): array
    {
        $optional = [
            'buyer_type' => fn () => TextColumn::make('buyer_type')->label('Kupac')->formatStateUsing(fn ($state) => match ((string) ($state instanceof BackedEnum ? $state->value : $state)) {
                'firm' => 'Firma',
                'private' => 'Fizičko lice',
                default => (string) ($state instanceof BackedEnum ? $state->value : $state),
            }),
            'buyer_name' => fn () => TextColumn::make('buyer_name')->label('Naziv kupca')->searchable()->placeholder('—'),
            'buyer_pib' => fn () => TextColumn::make('buyer_pib')->label('PIB')->placeholder('—')->toggleable(),
            'buyer_mb' => fn () => TextColumn::make('buyer_mb')->label('MB')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            'receipt_number' => fn () => TextColumn::make('receipt_number')->label('Broj računa')->searchable()->placeholder('—'),
            'consent_at' => fn () => TextColumn::make('consent_at')->label('Saglasnost')->dateTime('j. n. Y. H:i')->description(fn (Order $r) => static::hasColumn('consent_ip') ? $r->consent_ip : null)->toggleable(isToggledHiddenByDefault: true),
            'refund_of_receipt' => fn () => TextColumn::make('refund_of_receipt')->label('Storno računa')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            'refunded_at' => fn () => TextColumn::make('refunded_at')->label('Vraćeno')->dateTime('j. n. Y. H:i')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
        ];

        $out = [];
        foreach ($optional as $column => $make) {
            if (static::hasColumn($column)) {
                $out[] = $make();
            }
        }

        return $out;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ...static::columns(),
                ...static::paymentColumns(),
                TextColumn::make('note')->label('Napomena')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(Options::enum(OrderStatus::class)),
                SelectFilter::make('product_id')
                    ->label('Usluga')
                    ->options(fn () => Product::query()->orderBy('sort')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                OrderActions::markPaid(),
                OrderActions::activate(),
                OrderActions::refund(),
                OrderActions::cancel(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
