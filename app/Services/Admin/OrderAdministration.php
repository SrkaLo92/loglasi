<?php

namespace App\Services\Admin;

use App\Enums\OrderStatus;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderFulfillment;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Admin side of orders: "Označi kao plaćeno" (through OrderFulfillment) and cancelling. Writes the audit log. */
class OrderAdministration
{
    public function __construct(private readonly OrderFulfillment $fulfillment) {}

    /**
     * @throws DomainException when the order is cancelled
     */
    public function markPaid(Order $order, User $by, ?string $note = null, ?string $receiptNumber = null): Order
    {
        $order->refresh();

        if ($order->status === OrderStatus::Cancelled) {
            throw new DomainException('Otkazana narudžbina ne može da se označi kao plaćena.');
        }
        if ($order->status === OrderStatus::Paid) {
            return $order;
        }

        // receipt_number comes with the payments module; it is saved before the effect runs.
        if ($receiptNumber !== null && trim($receiptNumber) !== '' && Schema::hasColumn('orders', 'receipt_number')) {
            $order->forceFill(['receipt_number' => mb_substr(trim($receiptNumber), 0, 64)])->save();
        }

        $this->fulfillment->markPaid($order, $by);
        $order->refresh();

        if ($note !== null && trim($note) !== '') {
            $order->forceFill(['note' => trim($note)])->save();
        }

        $order->loadMissing(['product', 'listing', 'user.firm']);
        AuditLog::record('order.mark_paid', $order, array_filter([
            'reference' => $order->reference,
            'receipt_number' => $receiptNumber !== null && trim($receiptNumber) !== '' ? trim($receiptNumber) : null,
            'product' => $order->product?->code,
            'amount_rsd' => (int) $order->amount_rsd,
            'listing_id' => $order->listing_id,
            'featured_until' => $order->listing?->featured_until?->toIso8601String(),
            'biznis_until' => $order->user?->firm?->biznis_until?->toIso8601String(),
        ], fn ($v) => $v !== null));

        return $order;
    }

    public function activate(Order $order, User $by): Order
    {
        $this->fulfillment->activate($order, $by);
        AuditLog::record('order.activate', $order, ['reference' => $order->reference]);

        return $order->refresh();
    }

    public function refund(Order $order, User $by, string $reason): Order
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Upišite razlog povraćaja.');
        }

        $this->fulfillment->refund($order, $by, $reason);
        AuditLog::record('order.refund', $order->refresh(), ['reference' => $order->reference, 'reason' => $reason]);

        return $order;
    }

    /** Statuses after payment: "paid", and "activated" once the payments module adds it. */
    public static function paidStatuses(): array
    {
        return array_values(array_filter([OrderStatus::Paid, OrderStatus::tryFrom('activated')]));
    }

    /** @throws DomainException when the order is not pending */
    public function cancel(Order $order, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $note) {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Pending) {
                throw new DomainException('Otkazati se može samo narudžbina koja čeka uplatu.');
            }

            $locked->forceFill([
                'status' => OrderStatus::Cancelled,
                'note' => $note !== null && trim($note) !== '' ? trim($note) : $locked->note,
            ])->save();

            AuditLog::record('order.cancel', $locked, ['reference' => $locked->reference]);
            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });
    }
}
