<?php

namespace App\Http\Controllers\Notifications\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET/PATCH /me/notification-settings: the switches of "Obaveštenja". PATCH changes only the keys it sends
 * (unlike the site's form, where an unchecked box means off).
 */
class NotificationSettingsApiController extends Controller
{
    public function show(Request $request): Response
    {
        return ApiResponse::json($request, $this->payload($request), ApiResponse::CACHE_PRIVATE);
    }

    public function update(Request $request): Response
    {
        $keys = array_keys(NotificationPreferences::DEFAULTS);
        $data = $request->validate(array_fill_keys($keys, ['sometimes', 'boolean']));

        $values = NotificationPreferences::all($request->user());
        foreach ($data as $key => $value) {
            $values[$key] = (bool) $value;
        }
        NotificationPreferences::set($request->user(), $values);

        return ApiResponse::json($request, $this->payload($request), ApiResponse::CACHE_PRIVATE);
    }

    private function payload(Request $request): array
    {
        $values = NotificationPreferences::all($request->user());
        $items = [];
        foreach (NotificationPreferences::labels() as $key => $item) {
            $items[] = ['key' => $key, 'label' => $item['label'], 'help' => $item['help'], 'enabled' => (bool) $values[$key]];
        }

        return ['items' => $items];
    }
}
