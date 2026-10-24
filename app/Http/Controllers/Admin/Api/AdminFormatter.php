<?php

namespace App\Http\Controllers\Admin\Api;

use App\Http\Api\CatalogFormatter;
use App\Models\Order;
use App\Models\User;

/** AdminUser and Order shapes from the API contract. */
class AdminFormatter
{
    /** Order status in the contract. */
    public const ORDER_STATUS = [
        'pending' => 'awaiting_payment',
        'paid' => 'paid',
        'activated' => 'paid',
        'cancelled' => 'cancelled',
        'refunded' => 'cancelled',
    ];

    public static function user(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'phone_verified' => $user->isPhoneVerified(),
            'role' => $user->apiRole(),
            'active_listings' => (int) ($user->active_listings_count ?? 0),
            'registered_at' => self::date($user->created_at),
        ];
    }

    public static function order(Order $order): array
    {
        $status = $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status;

        return [
            'id' => (int) $order->id,
            'product' => $order->product ? CatalogFormatter::productCode($order->product) : null,
            'amount' => (int) $order->amount_rsd,
            'status' => self::ORDER_STATUS[$status] ?? $status,
            'listing_id' => $order->listing_id === null ? null : (int) $order->listing_id,
            'user' => $order->user ? ['id' => (int) $order->user->id, 'name' => $order->user->name] : null,
            'payment_reference' => $order->reference,
            'created_at' => self::date($order->created_at),
            'paid_at' => self::date($order->paid_at),
        ];
    }

    public static function date($date): ?string
    {
        return $date ? $date->copy()->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }
}
