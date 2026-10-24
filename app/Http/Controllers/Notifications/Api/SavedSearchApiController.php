<?php

namespace App\Http\Controllers\Notifications\Api;

use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\SavedSearches;
use App\Support\Notifications\SavedSearchQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * /me/saved-searches for the app: the same searches as "Sačuvane pretrage" on the site. A search is saved with
 * the site's query parameters (keyword, kategorija, mesto, price_min, price_max, currency, f[...]).
 */
class SavedSearchApiController extends Controller
{
    public function __construct(
        private readonly SavedSearchQuery $queries,
        private readonly SavedSearches $searches,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return ApiResponse::json($request, [
            'items' => $user->savedSearches()->latest('id')->get()->map(fn (SavedSearch $s) => $this->item($s))->values()->all(),
            'max' => SavedSearches::max(),
            'alerts_on' => NotificationPreferences::allows($user, NotificationPreferences::SAVED_SEARCHES),
        ], ApiResponse::CACHE_PRIVATE);
    }

    public function store(Request $request): Response
    {
        $request->validate(['name' => ['nullable', 'string', 'max:80']]);
        $query = $this->queries->normalize($request->only(['keyword', 'kategorija', 'mesto', 'price_min', 'price_max', 'currency', 'f']));
        $search = $this->searches->save($request->user(), $query, $request->input('name'));

        return ApiResponse::json($request, $this->item($search), ApiResponse::CACHE_PRIVATE, $search->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, SavedSearch $savedSearch): Response
    {
        Gate::authorize('update', $savedSearch);
        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notify' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $data)) {
            $savedSearch->name = SavedSearches::cleanName($data['name']) ?? $this->queries->describe((array) $savedSearch->query);
        }
        if (array_key_exists('notify', $data)) {
            $savedSearch->notify = (bool) $data['notify'];
        }
        $savedSearch->save();

        return ApiResponse::json($request, $this->item($savedSearch), ApiResponse::CACHE_PRIVATE);
    }

    public function destroy(SavedSearch $savedSearch): Response
    {
        Gate::authorize('delete', $savedSearch);
        $savedSearch->delete();

        return response()->noContent();
    }

    private function item(SavedSearch $search): array
    {
        return [
            'id' => $search->id,
            'name' => $search->name,
            'query' => (array) $search->query,
            'notify' => (bool) $search->notify,
            'url' => $search->url(),
            'created_at' => $search->created_at?->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];
    }
}
