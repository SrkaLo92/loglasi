<?php

namespace App\Http\Controllers\Notifications\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** POST /devices, DELETE /devices/{token}: FCM tokens of the signed-in app user. */
class DeviceController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512', 'regex:/^[A-Za-z0-9_:\-\.]+$/'],
            'platform' => ['required', 'string', Rule::in(['android', 'ios'])],
            'app_version' => ['nullable', 'string', 'max:32'],
        ], [
            'token.regex' => 'Neispravan token uređaja.',
            'token.min' => 'Neispravan token uređaja.',
        ]);

        $user = $request->user();

        // A token belongs to one installation: when another account signs in on the same phone it moves over.
        Device::query()->updateOrCreate(['token' => $data['token']], [
            'user_id' => $user->id,
            'platform' => $data['platform'],
            'app_version' => $data['app_version'] ?? null,
            'last_seen_at' => now(),
        ]);

        $max = (int) config('notifications.devices.max_per_user', 10);
        $keep = $user->devices()->orderByDesc('last_seen_at')->orderByDesc('id')->limit($max)->pluck('id');
        $user->devices()->whereNotIn('id', $keep)->delete();

        return response()->noContent();
    }

    /** Always 204: an unknown token or one of another account is not revealed. */
    public function destroy(Request $request, string $token): Response
    {
        $request->user()->devices()->where('token', $token)->delete();

        return response()->noContent();
    }
}
