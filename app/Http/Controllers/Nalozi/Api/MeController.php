<?php

namespace App\Http\Controllers\Nalozi\Api;

use App\Http\Controllers\Controller;
use App\Models\ListingImage;
use App\Models\User;
use App\Rules\PhoneNumber;
use App\Support\ApiError;
use App\Support\Auth\AccountDeletion;
use App\Support\Auth\Avatar;
use App\Support\ListingLimits;
use App\Support\Nalozi\AccountMenu;
use App\Support\Nalozi\AccountOverview;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** /me i /me/limits za mobilnu aplikaciju. */
class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(MePresenter::me($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('update', $user);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:80'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40', new PhoneNumber],
            'location_id' => ['sometimes', 'nullable', 'integer', Rule::exists('locations', 'id')],
            'avatar_media_id' => ['sometimes', 'nullable', 'integer'],
            'notifications' => ['sometimes', 'array'],
            'notifications.*' => ['boolean'],
        ]);

        if (array_key_exists('name', $data)) {
            $user->name = trim(preg_replace('/\s+/u', ' ', strip_tags($data['name'])));
        }
        if (array_key_exists('phone', $data)) {
            $phone = Phone::normalize($data['phone']);
            $user->phone = $phone !== '' ? $phone : null;
        }
        if (array_key_exists('location_id', $data)) {
            $user->forceFill(['location_id' => $data['location_id']]);
        }
        if (array_key_exists('notifications', $data)) {
            $known = array_intersect_key($data['notifications'], User::NOTIFICATION_DEFAULTS);
            $user->forceFill([
                'notification_settings' => array_map(fn ($v) => (bool) $v, $known + (array) $user->notification_settings),
            ]);
        }
        $user->save();

        if (array_key_exists('avatar_media_id', $data)) {
            $this->setAvatar($user, $data['avatar_media_id']);
        }

        return response()->json(MePresenter::me($user->fresh()));
    }

    public function destroy(Request $request): Response|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('delete', $user);

        $data = $request->validate(['password' => ['required', 'string', 'max:200']]);

        if (! Hash::check($data['password'], $user->getAuthPassword())) {
            return ApiError::response('invalid_credentials', 'Pogrešna lozinka.', 401, ['password' => 'Pogrešna lozinka.']);
        }

        AccountDeletion::delete($user);

        return response()->noContent();
    }

    public function limits(Request $request): JsonResponse
    {
        return response()->json(ListingLimits::for($request->user()));
    }

    /** GET /me/overview: the account overview of the site (counts, tasks, numbers for the last 30 days). */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $overview = new AccountOverview($user);
        $stats = $overview->stats();

        return response()->json([
            'counts' => AccountMenu::counts($user),
            'tasks' => array_map(fn (array $task) => [
                'type' => $task['type'],
                'count' => $task['count'],
                'text' => $task['text'],
                // Where the app opens it: my listings with a status tab, messages, orders or the profile.
                'target' => match ($task['type']) {
                    'unread_messages' => ['screen' => 'messages'],
                    'orders_awaiting_payment' => ['screen' => 'orders'],
                    'missing_location' => ['screen' => 'profile'],
                    'expiring' => ['screen' => 'my_listings', 'status' => 'active'],
                    'rejected' => ['screen' => 'my_listings', 'status' => 'rejected'],
                    'drafts' => ['screen' => 'drafts'],
                    default => ['screen' => 'my_listings', 'status' => 'pending'],
                },
            ], $overview->tasks()),
            'last_30_days' => ['views' => $stats['views'], 'phone_reveals' => $stats['reveals'], 'saves' => $stats['saved']],
            'limits' => ListingLimits::for($user),
            'plus_seller_until' => MePresenter::date($stats['plus_until']),
        ]);
    }

    /** Slika otpremljena preko POST /media postaje profilna; null je uklanja. */
    private function setAvatar(User $user, ?int $mediaId): void
    {
        if ($mediaId === null) {
            Avatar::delete($user);

            return;
        }

        $image = ListingImage::query()->whereKey($mediaId)->where('user_id', $user->id)->first();
        $path = $image ? Storage::disk('public')->path($image->path) : null;

        if ($path === null || ! is_file($path)) {
            throw ValidationException::withMessages(['avatar_media_id' => 'Slika nije pronađena. Otpremite je ponovo.']);
        }

        try {
            Avatar::storeFromFile($user, $path);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['avatar_media_id' => 'Slika ne može da se obradi. Probajte drugu sliku.']);
        }
    }
}
