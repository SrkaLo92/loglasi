<?php

namespace App\Http\Controllers\Payments\Api;

use App\Enums\OrderStatus;
use App\Http\Api\CatalogFormatter;
use App\Models\Order;
use DateTimeInterface;

/** The contract's Order schema. Also usable by the admin API (/admin/orders). */
class OrderPresenter
{
    /**
     * The contract knows three statuses. Activated is still "paid" for the app; a refunded order
     * is shown as "cancelled" until the contract gets a "refunded" value.
     */
    public const STATUSES = [
        'pending' => 'awaiting_payment',
        'paid' => 'paid',
        'activated' => 'paid',
        'refunded' => 'cancelled',
        'cancelled' => 'cancelled',
    ];

    /**
     * Contract status (awaiting_payment, paid, cancelled) → the OrderStatus values it covers,
     * for filters such as GET /admin/orders?status=paid.
     *
     * @return list<OrderStatus>
     */
    public static function statusesFromApi(string $status): array
    {
        return array_map(fn (string $value) => OrderStatus::from($value), array_keys(self::STATUSES, $status, true));
    }

    /** @return array<string, mixed> */
    public static function order(Order $order): array
    {
        $code = (string) $order->product?->code;

        return [
            'id' => (int) $order->id,
            'product' => CatalogFormatter::PRODUCT_CODES[$code] ?? $code,
            'amount' => (int) $order->amount_rsd,
            'status' => self::STATUSES[$order->status->value],
            'listing_id' => $order->listing_id !== null ? (int) $order->listing_id : null,
            'user' => ['id' => (int) $order->user_id, 'name' => (string) $order->user?->name],
            'payment_reference' => (string) $order->reference,
            'created_at' => self::date($order->created_at),
            'paid_at' => self::date($order->paid_at),
        ];
    }

    private static function date(?DateTimeInterface $date): ?string
    {
        return $date ? $date->copy()->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }
}
