<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderFulfillment;
use App\Services\OrderService;
use App\Services\Payments\PaymentConfirmation;
use Carbon\CarbonInterface;
use DomainException;

/**
 * Admin side of orders. The work is done by the payments module (OrderFulfillment, OrderService),
 * which locks the order, is idempotent and writes the audit log; this class only checks input.
 */
class OrderAdministration
{
    public function __construct(
        private readonly OrderFulfillment $fulfillment,
        private readonly OrderService $orders,
    ) {}

    public function markPaid(
        Order $order,
        User $by,
        ?string $receiptNumber = null,
        ?string $advanceReceiptNumber = null,
        ?CarbonInterface $paidAt = null,
        ?string $note = null,
    ): Order {
        if ($paidAt !== null && $paidAt->isFuture()) {
            throw new DomainException('Datum uplate ne može biti u budućnosti.');
        }
        if ($order->status->isPaid()) {
            throw new DomainException('Narudžbina je već plaćena.');
        }

        $this->fulfillment->markPaid($order, $by, new PaymentConfirmation(
            paidAt: $paidAt,
            receiptNumber: self::clean($receiptNumber),
            advanceReceiptNumber: self::clean($advanceReceiptNumber),
        ));
        $this->appendNote($order, $note);

        return $order;
    }

    /** Retries activation of a paid order (e.g. after the listing was restored). */
    public function activate(Order $order, User $by): Order
    {
        if (! $this->fulfillment->activate($order, $by)) {
            throw new DomainException('Narudžbina ne čeka aktivaciju.');
        }

        return $order;
    }

    public function recordReceipt(Order $order, User $by, ?string $receiptNumber, ?string $advanceReceiptNumber = null): Order
    {
        if (self::clean($receiptNumber) === null && self::clean($advanceReceiptNumber) === null) {
            throw new DomainException('Upišite broj računa.');
        }

        $this->fulfillment->recordReceipt($order, new PaymentConfirmation(
            receiptNumber: self::clean($receiptNumber),
            advanceReceiptNumber: self::clean($advanceReceiptNumber),
        ), $by);

        return $order;
    }

    /** Pro-rata by default; $amountRsd overrides it (capped at the paid amount by the payments module). */
    public function refund(Order $order, User $by, string $reason, ?string $refundReceiptNumber = null, ?int $amountRsd = null, ?string $buyerId = null): Order
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Upišite razlog povraćaja.');
        }
        if ($amountRsd !== null && ($amountRsd < 0 || $amountRsd > (int) $order->amount_rsd)) {
            throw new DomainException('Iznos povraćaja mora biti između 0 i plaćenog iznosa.');
        }
        if (! $this->fulfillment->refund($order, self::clean($refundReceiptNumber), 'Povraćaj: '.$reason, $by, $amountRsd, self::clean($buyerId))) {
            throw new DomainException('Narudžbina je već refundirana.');
        }

        return $order;
    }

    public function refundableAmount(Order $order): int
    {
        return $this->fulfillment->refundableAmount($order);
    }

    public function cancel(Order $order, User $by, ?string $note = null): Order
    {
        if (! $this->orders->cancel($order, $by, 'order.cancelled_by_admin')) {
            throw new DomainException('Otkazati se može samo narudžbina koja čeka uplatu.');
        }
        $this->appendNote($order, $note);

        return $order;
    }

    /** @return list<OrderStatus> */
    public static function paidStatuses(): array
    {
        return [OrderStatus::Paid, OrderStatus::Activated];
    }

    private function appendNote(Order $order, ?string $note): void
    {
        $note = trim((string) $note);
        if ($note !== '') {
            $order->forceFill(['note' => trim(($order->note ? $order->note."\n" : '').mb_substr($note, 0, 1000))])->save();
        }
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 64);
    }
}
