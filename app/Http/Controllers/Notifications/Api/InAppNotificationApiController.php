<?php

namespace App\Http\Controllers\Notifications\Api;

use App\Http\Controllers\Controller;
use App\Services\Notifications\InAppNotifications;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The bell of the apps: GET /me/notifications (newest first, ?before=<id> for the next page),
 * GET /me/notifications/unread-count, POST /me/notifications/read ({"ids":[…]} or all).
 */
class InAppNotificationApiController extends Controller
{
    public function __construct(private InAppNotifications $inApp) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'before' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $user = $request->user();
        $page = $this->inApp->page($user, isset($data['before']) ? (int) $data['before'] : null, (int) ($data['per_page'] ?? InAppNotifications::PER_PAGE));

        return response()->json([
            'data' => $page['items']->map(fn ($n) => $this->inApp->toApi($n))->all(),
            'next_before' => $page['next'],
            'unread' => $this->inApp->unread($user),
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['unread' => $this->inApp->unread($request->user())]);
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['sometimes', 'array', 'max:200'],
            'ids.*' => ['integer', 'min:1'],
        ]);
        $user = $request->user();
        $marked = $this->inApp->markRead($user, isset($data['ids']) ? array_map('intval', $data['ids']) : null);

        return response()->json(['marked' => $marked, 'unread' => $this->inApp->unread($user)]);
    }
}
