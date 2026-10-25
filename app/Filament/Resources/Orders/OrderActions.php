<?php

namespace App\Filament\Resources\Orders;

use App\Enums\BuyerType;
use App\Enums\OrderStatus;
use App\Filament\Support\Notify;
use App\Models\Order;
use App\Services\Admin\OrderAdministration;
use App\Services\Payments\FiscalDriver;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

/** Order actions; the payments module does the work (see OrderAdministration). */
class OrderActions
{
    private static function service(): OrderAdministration
    {
        return app(OrderAdministration::class);
    }

    private static function money(int $amount): string
    {
        return number_format($amount, 0, ',', '.')."\u{00A0}RSD";
    }

    public static function markPaid(): Action
    {
        return Action::make('markPaid')
            ->label('Označi kao plaćeno')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Order $record) => in_array($record->status, [OrderStatus::Pending, OrderStatus::Cancelled], true))
            ->fillForm(['paid_at' => now()->toDateString()])
            ->schema([
                DatePicker::make('paid_at')->label('Datum uplate')->required()->maxDate(now()),
                TextInput::make('receipt_number')
                    ->label(fn (Order $record) => $record->buyer_type === BuyerType::Firm ? 'Broj fakture' : 'Broj fiskalnog računa')
                    ->helperText(fn (Order $record) => match (true) {
                        $record->buyer_type === BuyerType::Firm => 'Prazno = sledeći broj fakture (npr. 12/2026).',
                        FiscalDriver::automatic() => 'Prazno = fiskalni račun se izdaje automatski preko V-PFR-a.',
                        default => 'Može i kasnije, akcijom „Upiši broj računa”.',
                    })
                    ->maxLength(64),
                TextInput::make('advance_receipt_number')
                    ->label('Broj avansnog računa')
                    ->visible(fn (Order $record) => $record->buyer_type !== BuyerType::Firm)
                    ->maxLength(64),
                Textarea::make('note')->label('Napomena (nije obavezno)')->rows(2)->maxLength(1000),
            ])
            ->modalHeading(fn (Order $record) => 'Uplata za '.$record->reference)
            ->modalDescription(fn (Order $record) => sprintf(
                'Proverite da je na račun stiglo %s sa pozivom na broj %s. Usluga („%s”) se uključuje odmah.%s',
                self::money((int) $record->amount_rsd),
                $record->reference,
                $record->product?->name,
                $record->status === OrderStatus::Cancelled ? ' Narudžbina je bila otkazana; uplata je stigla kasnije.' : '',
            ))
            ->modalSubmitActionLabel('Plaćeno, uključi uslugu')
            ->action(fn (Order $record, array $data) => Notify::run(
                fn () => self::service()->markPaid(
                    $record,
                    auth()->user(),
                    $data['receipt_number'] ?? null,
                    $data['advance_receipt_number'] ?? null,
                    filled($data['paid_at'] ?? null) ? Carbon::parse($data['paid_at'])->setTimeFrom(now()) : null,
                    $data['note'] ?? null,
                ),
                'Uplata je evidentirana.',
            ));
    }

    /** A paid order whose service could not be switched on (listing deleted, no firm profile). */
    public static function activate(): Action
    {
        return Action::make('activate')
            ->label('Aktiviraj uslugu')
            ->icon(Heroicon::OutlinedBolt)
            ->color('primary')
            ->visible(fn (Order $record) => $record->status === OrderStatus::Paid)
            ->requiresConfirmation()
            ->modalDescription('Plaćena usluga nije uključena. Pokušaj ponovo, na primer posle vraćanja oglasa.')
            ->action(fn (Order $record) => Notify::run(fn () => self::service()->activate($record, auth()->user()), 'Usluga je uključena.'));
    }

    public static function recordReceipt(): Action
    {
        return Action::make('recordReceipt')
            ->label('Upiši broj računa')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->visible(fn (Order $record) => $record->status->isPaid() && $record->receipt_number === null)
            ->schema([
                TextInput::make('receipt_number')->label('Broj računa')->maxLength(64),
                TextInput::make('advance_receipt_number')
                    ->label('Broj avansnog računa')
                    ->visible(fn (Order $record) => $record->buyer_type !== BuyerType::Firm)
                    ->maxLength(64),
            ])
            ->action(fn (Order $record, array $data) => Notify::run(
                fn () => self::service()->recordReceipt($record, auth()->user(), $data['receipt_number'] ?? null, $data['advance_receipt_number'] ?? null),
                'Broj računa je upisan.',
            ));
    }

    /** Pro-rata refund for unused days (OrderFulfillment::refundableAmount); the admin may enter another amount. */
    public static function refund(): Action
    {
        return Action::make('refund')
            ->label('Vrati novac')
            ->icon(Heroicon::OutlinedReceiptRefund)
            ->color('danger')
            ->visible(fn (Order $record) => in_array($record->status, OrderAdministration::paidStatuses(), true))
            ->fillForm(fn (Order $record) => ['amount_rsd' => self::service()->refundableAmount($record)])
            ->schema([
                TextInput::make('amount_rsd')
                    ->label('Iznos povraćaja (din)')
                    ->helperText(fn (Order $record) => sprintf(
                        'Srazmerno neiskorišćenom delu: %s od plaćenih %s.',
                        self::money(self::service()->refundableAmount($record)),
                        self::money((int) $record->amount_rsd),
                    ))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(fn (Order $record) => (int) $record->amount_rsd)
                    ->required(),
                TextInput::make('refund_receipt_number')->label('Broj storno računa / knjižnog odobrenja')
                    ->helperText(fn (Order $record) => $record->buyer_type === BuyerType::Firm || FiscalDriver::automatic() ? 'Prazno = izdaje se automatski.' : null)
                    ->maxLength(64),
                Textarea::make('reason')->label('Razlog povraćaja')->required()->rows(2)->maxLength(1000),
            ])
            ->modalDescription(fn (Order $record) => 'Usluga se isključuje. Novac se vraća uplatom sa računa firme; ovde se samo evidentira. '
                .($record->consent_at
                    ? 'Kupac je dao saglasnost za početak usluge '.$record->consent_at->format('j. n. Y. \u H:i').'.'
                    : 'Nema saglasnosti potrošača za početak usluge (firma ili stara narudžbina).'))
            ->modalSubmitActionLabel('Evidentiraj povraćaj')
            ->action(fn (Order $record, array $data) => Notify::run(
                fn () => self::service()->refund($record, auth()->user(), (string) $data['reason'], $data['refund_receipt_number'] ?? null, (int) $data['amount_rsd']),
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
                fn () => self::service()->cancel($record, auth()->user(), $data['note'] ?? null),
                'Narudžbina je otkazana.',
            ));
    }
}
