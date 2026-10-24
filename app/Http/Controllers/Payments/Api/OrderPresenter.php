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
     * Contract statuses: awaiting_payment, paid, cancelled, plus "refunded" (money returned; see
     * "Mobile contract changes" in docs/modules/payments.md). Activated is still "paid" for the app.
     */
    public const STATUSES = [
        'pending' => 'awaiting_payment',
        'paid' => 'paid',
        'activated' => 'paid',
        'refunded' => 'refunded',
        'cancelled' => 'cancelled',
    ];

    /**
     * Contract status (awaiting_payment, paid, refunded, cancelled) → the OrderStatus values it covers,
     * for filters such as GET /admin/orders?status=paid.
     *
     * @return list<OrderStatus>
     */
    public static function statusesFromApi(string $status): array
    {
        return array_map(fn (string $value) => OrderStatus::from($value), array_keys(self::STATUSES, $status, true));
    }

    /**
     * `product` is the contract code where the contract has one (bump, featured_7d, featured_30d,
     * business_year), otherwise the products.code as is (premium_xl_7, business_pro_monthly, ...);
     * `product_label` is the product name in Serbian. Load the product with code and name.
     *
     * @return array<string, mixed>
     */
    public static function order(Order $order): array
    {
        $code = (string) $order->product?->code;

        return [
            'id' => (int) $order->id,
            'product' => CatalogFormatter::PRODUCT_CODES[$code] ?? $code,
            'product_label' => (string) ($order->product?->name ?? $code),
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
