<?php

namespace App\Services\Payments;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * LATER PHASE, not implemented: card payments through a bank's payment page (needs a bank contract).
 * Flow: start() returns the URL of the bank's page for a pending order; the bank calls back and
 * handleCallback() verifies the signature and returns the payment, which is then passed to
 * OrderFulfillment::markPaid(). A card payment always gets a fiscal receipt ("platna kartica").
 */
interface CardPaymentGateway
{
    /** URL of the bank's payment page for this order. */
    public function start(Order $order, string $returnUrl): string;

    /** Verified payment from the bank's callback, or null when it is not a successful, genuine payment. */
    public function handleCallback(Request $request): ?IncomingPayment;
}
