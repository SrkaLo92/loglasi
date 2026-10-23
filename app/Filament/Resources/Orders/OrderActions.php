<?php

namespace App\Filament\Resources\Orders;

use App\Enums\OrderStatus;
use App\Filament\Support\Notify;
use App\Models\Order;
use App\Services\Admin\OrderAdministration;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class OrderActions
{
    public static function markPaid(): Action
    {
        return Action::make('markPaid')
            ->label('Označi kao plaćeno')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Order $record) => $record->status === OrderStatus::Pending)
            ->schema(fn () => array_values(array_filter([
                OrderResource::hasColumn('receipt_number')
                    ? TextInput::make('receipt_number')
                        ->label('Broj računa')
                        ->helperText('Fiskalni račun za fizička lica, faktura za firme.')
                        ->required()
                        ->maxLength(64)
                    : null,
                Textarea::make('note')->label('Napomena (nije obavezno)')->rows(2)->maxLength(1000),
            ])))
            ->modalHeading(fn (Order $record) => 'Uplata za '.$record->reference)
            ->modalDescription(fn (Order $record) => sprintf(
                'Proverite da je na račun stiglo %s din sa pozivom na broj %s. Usluga („%s”) se uključuje odmah.',
                number_format((int) $record->amount_rsd, 0, ',', '.'),
                $record->reference,
                $record->product?->name,
            ))
            ->modalSubmitActionLabel('Plaćeno, uključi uslugu')
            ->action(fn (Order $record, array $data) => Notify::run(
                fn () => app(OrderAdministration::class)->markPaid($record, auth()->user(), $data['note'] ?? null, $data['receipt_number'] ?? null),
                'Uplata je evidentirana i usluga je uključena.',
            ));
    }

    /** Shown once the payments module adds the "activated" status. */
    public static function activate(): Action
    {
        return Action::make('activate')
            ->label('Aktiviraj')
            ->icon(Heroicon::OutlinedBolt)
            ->color('primary')
            ->visible(fn (Order $record) => OrderStatus::tryFrom('activated') !== null && $record->status === OrderStatus::Paid)
            ->requiresConfirmation()
            ->modalDescription('Usluga se uključuje za kupca.')
            ->action(fn (Order $record) => Notify::run(
                fn () => app(OrderAdministration::class)->activate($record, auth()->user()),
                'Narudžbina je aktivirana.',
            ));
    }

    /** Pro-rata refund through the payments module; shown once it adds the "refunded" status. */
    public static function refund(): Action
    {
        return Action::make('refund')
            ->label('Vrati novac')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('danger')
            ->visible(fn (Order $record) => OrderStatus::tryFrom('refunded') !== null && in_array($record->status, OrderAdministration::paidStatuses(), true))
            ->schema([
                Textarea::make('reason')->label('Razlog povraćaja')->required()->rows(2)->maxLength(1000),
            ])
            ->modalDescription('Iznos se računa srazmerno preostalom trajanju usluge. Izdaje se storno računa.')
            ->modalSubmitActionLabel('Vrati novac')
            ->action(fn (Order $record, array $data) => Notify::run(
                fn () => app(OrderAdministration::class)->refund($record, auth()->user(), $data['reason']),
                'Povraćaj je evidentiran.',
            ));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Otkaži')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Order $record) => $record->status === OrderStatus::Pending)
            ->schema([
                Textarea::make('note')->label('Razlog (nije obavezno)')->rows(2)->maxLength(1000),
            ])
            ->modalHeading('Otkazati narudžbinu?')
            ->modalSubmitActionLabel('Otkaži narudžbinu')
            ->action(fn (Order $record, array $data) => Notify::run(
                fn () => app(OrderAdministration::class)->cancel($record, $data['note'] ?? null),
                'Narudžbina je otkazana.',
            ));
    }
}
