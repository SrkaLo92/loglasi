<?php

namespace App\Http\Controllers\Payments\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** GET /me/orders: the app only shows order status; buying happens on the website. */
class OrderApiController extends Controller
{
    /** Newest first; capped, since a user has few orders. */
    public const LIMIT = 200;

    public function index(Request $request): Response|JsonResponse
    {
        $user = $request->user();

        $orders = Order::query()
            ->where('user_id', $user->getKey())
            ->with('product:id,code')
            ->latest('created_at')->latest('id')
            ->limit(self::LIMIT)
            ->get()
            ->each(fn (Order $order) => $order->setRelation('user', $user))
            ->map(fn (Order $order) => OrderPresenter::order($order))
            ->values();

        return ApiResponse::json($request, $orders, ApiResponse::CACHE_PRIVATE);
    }
}
