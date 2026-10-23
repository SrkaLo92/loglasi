<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use App\Support\Notifications\NotificationPreferences;
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
    public function __construct(private readonly SavedSearchQuery $queries) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('notifications.saved-searches', [
            'searches' => $user->savedSearches()->latest('id')->get(['id', 'name', 'query', 'notify', 'last_notified_at', 'created_at']),
            'alertsOn' => NotificationPreferences::allows($user, NotificationPreferences::SAVED_SEARCHES),
            'max' => $this->max(),
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

            // The form is on the results page of another module: errors are shown on the list.
            return redirect()->to(url_slash('moj-nalog/pretrage'))->withErrors($e->errors());
        }
    }

    private function save(Request $request): RedirectResponse|JsonResponse
    {
        $user = $request->user();

        $request->validate(['name' => ['nullable', 'string', 'max:80']]);
        $query = $this->queries->normalize($request->only(['keyword', 'kategorija', 'mesto', 'price_min', 'price_max', 'currency', 'f']));
        $hash = SavedSearchQuery::hash($query);

        $search = $user->savedSearches()->where('query_hash', $hash)->first();
        $created = false;

        if ($search === null) {
            if ($user->savedSearches()->count() >= $this->max()) {
                throw ValidationException::withMessages([
                    'keyword' => 'Možete sačuvati najviše '.$this->max().' pretraga. Obrišite neku koju više ne koristite.',
                ]);
            }

            // createOrFirst: a double click saves one row (unique user_id + query_hash).
            $search = $user->savedSearches()->createOrFirst(['query_hash' => $hash], [
                'name' => $this->cleanName($request->input('name')) ?? $this->queries->describe($query),
                'query' => $query,
                'notify' => true,
            ]);
            $created = $search->wasRecentlyCreated;
        }

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

        if (array_key_exists('name', $data)) {
            $savedSearch->name = $this->cleanName($data['name']) ?? $this->queries->describe((array) $savedSearch->query);
        }
        if (array_key_exists('notify', $data)) {
            $savedSearch->notify = (bool) $data['notify'];
        }
        $savedSearch->save();

        return redirect()->to(url_slash('moj-nalog/pretrage'))->with('status', 'Pretraga je sačuvana.');
    }

    public function destroy(SavedSearch $savedSearch): RedirectResponse
    {
        Gate::authorize('delete', $savedSearch);
        $savedSearch->delete();

        return redirect()->to(url_slash('moj-nalog/pretrage'))->with('status', 'Pretraga je obrisana.');
    }

    private function cleanName(mixed $name): ?string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $name)));

        return $name === '' ? null : mb_substr($name, 0, 80);
    }

    private function max(): int
    {
        return (int) config('notifications.saved_searches.max_per_user', 20);
    }
}
