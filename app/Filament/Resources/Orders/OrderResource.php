<?php

namespace App\Filament\Resources\Orders;

use App\Enums\BuyerType;
use App\Enums\OrderStatus;
use App\Filament\Resources\Listings\ListingResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\CsvExport;
use App\Filament\Support\Options;
use App\Filament\Support\StaffAccess;
use App\Models\Order;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Bank transfer orders. No form; every write is an action that goes through the payments module. */
class OrderResource extends Resource
{
    use StaffAccess;

    protected static bool $adminOnly = true;

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
                ->formatStateUsing(fn ($state) => number_format((int) $state, 0, ',', '.')."\u{00A0}RSD")
                ->sortable(),
            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                ->color(fn (OrderStatus $state) => match ($state) {
                    OrderStatus::Pending => 'warning',
                    OrderStatus::Paid => 'info',
                    OrderStatus::Activated => 'success',
                    OrderStatus::Refunded => 'danger',
                    OrderStatus::Cancelled => 'gray',
                    default => 'info',
                }),
            TextColumn::make('created_at')->label('Naručeno')->dateTime('j. n. Y. H:i')->sortable(),
            TextColumn::make('paid_at')->label('Plaćeno')->dateTime('j. n. Y. H:i')->placeholder('—')->sortable(),
        ]));
    }

    /** @return list<TextColumn> */
    public static function paymentColumns(): array
    {
        return [
            TextColumn::make('buyer_type')->label('Kupac')->formatStateUsing(fn (?BuyerType $state) => match ($state) {
                BuyerType::Firm => 'Firma',
                BuyerType::Private => 'Fizičko lice',
                default => '—',
            }),
            TextColumn::make('buyer_name')->label('Naziv kupca')->searchable()->placeholder('—'),
            TextColumn::make('buyer_pib')->label('PIB')->placeholder('—')->toggleable(),
            TextColumn::make('buyer_mb')->label('MB')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('receipt_number')->label('Broj računa')->searchable()->placeholder('—')
                ->description(fn (Order $r) => $r->advance_receipt_number ? 'Avansni: '.$r->advance_receipt_number : null),
            TextColumn::make('service_ends_at')->label('Usluga do')->dateTime('j. n. Y.')->placeholder('—')->toggleable(),
            // Consumer's consent that the service starts on payment (withdrawal right ends for the performed part).
            TextColumn::make('consent_at')->label('Saglasnost za početak usluge')->dateTime('j. n. Y. H:i')->placeholder('—')
                ->description(fn (Order $r) => $r->consent_at ? trim('IP '.$r->consent_ip) : null)
                ->tooltip(fn (Order $r) => $r->terms_accepted_at ? 'Uslovi prihvaćeni '.$r->terms_accepted_at->format('j. n. Y. H:i') : null)
                ->toggleable(),
            TextColumn::make('refund_amount_rsd')->label('Vraćeno')
                ->formatStateUsing(fn ($state) => number_format((int) $state, 0, ',', '.')."\u{00A0}RSD")
                ->description(fn (Order $r) => $r->refund_receipt_number ? 'Storno: '.$r->refund_receipt_number : null)
                ->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('is_test')->label('Test')->formatStateUsing(fn ($state) => $state ? 'TEST' : '')->badge()->color('warning')->toggleable(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CsvExport::action('narudzbine', [
                    'Poziv na broj' => fn (Order $o) => $o->reference,
                    'Kupac' => fn (Order $o) => $o->user?->name,
                    'E-pošta' => fn (Order $o) => $o->user?->email,
                    'Usluga' => fn (Order $o) => $o->product?->name,
                    'Oglas' => fn (Order $o) => $o->listing_id,
                    'Iznos (din)' => fn (Order $o) => $o->amount_rsd,
                    'Status' => fn (Order $o) => $o->status,
                    'Test' => fn (Order $o) => (bool) $o->is_test,
                    'Kreirana' => fn (Order $o) => $o->created_at,
                    'Plaćena' => fn (Order $o) => $o->paid_at,
                ], ['user', 'product']),
            ])
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
                TernaryFilter::make('is_test')->label('Test narudžbine')->trueLabel('Samo test')->falseLabel('Bez test'),
            ])
            ->paginationMode(PaginationMode::Simple)
            ->recordActions([
                OrderActions::markPaid(),
                ActionGroup::make([
                    OrderActions::activate(),
                    OrderActions::recordReceipt(),
                    OrderActions::refund(),
                    OrderActions::cancel(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
        ];
    }
}
