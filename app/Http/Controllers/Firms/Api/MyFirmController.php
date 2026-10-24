<?php

namespace App\Http\Controllers\Firms\Api;

use App\Http\Api\ApiResponse;
use App\Http\Api\ListingFormatter;
use App\Http\Controllers\Controller;
use App\Models\ListingImage;
use App\Support\ApiError;
use App\Support\Firms\FirmAccount;
use App\Support\Firms\FirmImages;
use App\Support\Firms\FirmProfileUpdater;
use App\Support\Firms\FirmStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** GET/PATCH /me/firm and GET /me/firm/stats (firm accounts only; others get 404 not_found). */
class MyFirmController extends Controller
{
    /** Contract periods → number of days. */
    public const PERIODS = ['today' => 1, '7d' => 7, '30d' => 30];

    public function show(Request $request): JsonResponse|Response
    {
        $user = $request->user();
        $firm = FirmAccount::ensureSaved(FirmAccount::firmFor($user));
        Gate::authorize('update', $firm);

        return ApiResponse::json($request, ListingFormatter::firm($firm), ApiResponse::CACHE_PRIVATE);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $firm = FirmAccount::firmFor($user);
        Gate::authorize('update', $firm);

        $data = $request->validate(FirmProfileUpdater::apiRules());

        DB::transaction(function () use ($firm, $data) {
            FirmProfileUpdater::fillFromApi($firm, $data);
            $firm->save();
        });

        if (array_key_exists('logo_media_id', $data)) {
            $this->setLogo($firm, $user->id, $data['logo_media_id']);
        }

        return new JsonResponse(ListingFormatter::firm(FirmAccount::ensureSaved($firm)->fresh()), 200, [], ApiResponse::JSON_FLAGS);
    }

    public function stats(Request $request): JsonResponse|Response
    {
        $user = $request->user();
        $firm = FirmAccount::firmFor($user);
        Gate::authorize('update', $firm);

        $data = $request->validate(['period' => ['sometimes', 'string', Rule::in(array_keys(self::PERIODS))]]);

        if (! Gate::allows('viewStats', $firm)) {
            return ApiError::response('package_required', 'Statistika je deo paketa za firme. Paket birate na sajtu.', 403);
        }

        $stats = FirmStats::daily($user->id, self::PERIODS[$data['period'] ?? '7d']);

        return ApiResponse::json($request, [
            'views' => $stats['totals']['views'],
            'contacts' => $stats['totals']['phone_reveals'],
            'messages' => $stats['totals']['messages'],
            'favorites' => $stats['totals']['favorites'],
            'daily' => array_map(fn ($point) => ['date' => $point['date'], 'value' => $point['views']], $stats['daily']),
        ], ApiResponse::CACHE_PRIVATE);
    }

    /** Image uploaded with POST /media becomes the logo; null removes it. */
    private function setLogo($firm, int $userId, ?int $mediaId): void
    {
        if ($mediaId === null) {
            FirmImages::delete($firm, 'logo_path');

            return;
        }

        $image = ListingImage::query()->whereKey($mediaId)->where('user_id', $userId)->first();
        $path = $image ? Storage::disk('public')->path($image->path) : null;

        if ($path === null || ! is_file($path)) {
            throw ValidationException::withMessages(['logo_media_id' => 'Slika nije pronađena. Otpremite je ponovo.']);
        }

        try {
            FirmImages::storeLogo(FirmAccount::ensureSaved($firm), $path);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['logo_media_id' => 'Slika ne može da se obradi. Probajte drugu sliku.']);
        }
    }
}
