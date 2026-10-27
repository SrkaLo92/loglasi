<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use App\Support\Auth\LoginPage;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\SavedSearches;
use App\Support\Notifications\SavedSearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * /moj-nalog/pretrage/: "Sačuvane pretrage". The "Sačuvaj pretragu" button on /oglasi/
 * posts the same query parameters here (keyword, kategorija, mesto, price_min, price_max,
 * currency, f[...]); a JSON request gets 201/200 with the search instead of a redirect.
 */
class SavedSearchController extends Controller
{
    public function __construct(
        private readonly SavedSearchQuery $queries,
        private readonly SavedSearches $searches,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('notifications.saved-searches', [
            'searches' => $user->savedSearches()->latest('id')->get(['id', 'name', 'query', 'notify', 'last_notified_at', 'created_at']),
            'alertsOn' => NotificationPreferences::allows($user, NotificationPreferences::SAVED_SEARCHES),
            'max' => SavedSearches::max(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        try {
            return $this->save($request);
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                throw $e;
            }

            // The form is on the results page of another module: errors are shown on the list
            // (a guest has no list yet, so back to the results).
            return $request->user() === null
                ? redirect()->back(302, [], url_slash('oglasi'))->withErrors($e->errors())
                : redirect()->to(url_slash('moj-nalog/pretrage'))->withErrors($e->errors());
        }
    }

    private function save(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate(['name' => ['nullable', 'string', 'max:80']]);
        $query = $this->queries->normalize($request->only(['keyword', 'kategorija', 'mesto', 'price_min', 'price_max', 'currency', 'f']));

        // Guest: keep the search in the session, it is saved right after login or registration.
        if ($request->user() === null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Prijavite se da biste sačuvali pretragu.'], 401);
            }
            $request->session()->put(SavedSearches::PENDING, ['query' => $query, 'name' => SavedSearches::cleanName($request->input('name'))]);
            $request->session()->put('url.intended', url_slash('moj-nalog/pretrage'));

            return redirect()->to(LoginPage::url())
                ->with('status', 'Prijavite se ili napravite nalog, a pretragu ćemo sačuvati odmah posle toga.');
        }

        $search = $this->searches->save($request->user(), $query, $request->input('name'));
        $created = $search->wasRecentlyCreated;

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $search->id,
                'name' => $search->name,
                'notify' => $search->notify,
                'url' => $search->url(),
            ], $created ? 201 : 200);
        }

        return redirect()->to(url_slash('moj-nalog/pretrage'))->with('status', $created
            ? 'Pretraga je sačuvana. Javićemo vam kad stignu novi oglasi.'
            : 'Ova pretraga je već sačuvana.');
    }

    public function update(Request $request, SavedSearch $savedSearch): RedirectResponse
    {
        Gate::authorize('update', $savedSearch);

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:80'],
            'notify' => ['sometimes', 'boolean'],
        ]);

        $this->searches->update($savedSearch, $data);

        return redirect()->to(url_slash('moj-nalog/pretrage'))->with('status', 'Pretraga je sačuvana.');
    }

    public function destroy(SavedSearch $savedSearch): RedirectResponse
    {
        Gate::authorize('delete', $savedSearch);
        $savedSearch->delete();

        return redirect()->to(url_slash('moj-nalog/pretrage'))->with('status', 'Pretraga je obrisana.');
    }
}
