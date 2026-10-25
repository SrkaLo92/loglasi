<?php

namespace App\Http\Controllers\Listing\Api;

use App\Http\Api\ApiError;
use App\Http\Api\ApiResponse;
use App\Http\Api\ListingFormatter;
use App\Http\Controllers\Controller;
use App\Models\Firm;
use App\Support\Home\HomeSections;
use App\Support\Home\HomeSettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** GET /firms/{id}: public firm profile; GET /home/firms: the firm cards of the home page. */
class FirmApiController extends Controller
{
    public function show(Request $request, int $id): Response
    {
        $firm = $id > 0 ? Firm::query()->with(['user', 'location'])->find($id) : null;
        $user = $firm?->user;

        if ($user === null || ! $user->isFirm() || $user->isBlocked()) {
            throw ApiError::notFound('Firma ne postoji.');
        }

        return ApiResponse::json($request, ListingFormatter::firm($firm), ApiResponse::CACHE_CATALOG);
    }

    /** The same cards as "Firme iz Loznice" on the site's home page; empty items = hide the section. */
    public function home(Request $request): Response
    {
        $section = HomeSections::get()['firms'];

        return ApiResponse::json($request, [
            'title' => HomeSettings::get('home.firms_title'),
            'text' => HomeSettings::get('home.firms_text') ?: null,
            'directory_total' => (int) ($section['directory_total'] ?? 0),
            'directory_url' => url_slash('/firme'),
            'items' => array_map(fn (array $f) => [
                'id' => (int) ($f['id'] ?? 0),
                'name' => $f['name'],
                'activity' => $f['activity'] !== '' ? $f['activity'] : null,
                'place' => $f['place'] !== '' ? $f['place'] : null,
                'logo' => $f['logo'] ? url($f['logo']) : null,
                'cover' => $f['cover'] ? url($f['cover']) : null,
                'initials' => $f['initials'],
                'active_listings' => (int) $f['count'],
                'url' => $f['url'],
            ], array_values(array_filter($section['items'], fn (array $f) => isset($f['id'])))),
        ]);
    }
}
