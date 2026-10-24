<?php

namespace App\Http\Controllers\Messages;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\ListingSearch;
use App\Services\Messages\Favorites;
use Illuminate\Http\Request;

/**
 * /moj-nalog/omiljeni/: saved listings. The listing card heart always POSTs, so POST toggles
 * (save, or remove when already saved); DELETE always removes. Works without JS.
 */
class FavoriteController extends Controller
{
    public function __construct(private Favorites $favorites) {}

    public function index(Request $request)
    {
        $cursor = is_string($request->query('cursor')) ? $request->query('cursor') : null;
        $page = $this->favorites->page($request->user(), $cursor, (int) config('messages.favorites_per_page'), 1, ListingSearch::cardRelations());

        return view('messages.favorites', [
            'items' => $page['items']->map(fn (Listing $l) => [
                'listing' => $l,
                'state' => match (true) {
                    $l->status === ListingStatus::Sold => 'Prodato',
                    ! $l->isActive() => 'Istekao',
                    default => null,
                },
            ]),
            'next' => $page['next'],
            'isFirstPage' => $cursor === null,
        ]);
    }

    public function store(Request $request, int $listing)
    {
        if ($this->favorites->has($request->user(), $listing)) {
            return $this->destroy($request, $listing);
        }

        $model = Listing::query()->find($listing);
        if (! $model || ! $this->favorites->canSave($model)) {
            return $this->respond($request, false, 'Oglas ne postoji ili više nije aktivan.', 404);
        }

        $this->favorites->add($request->user(), $model);

        return $this->respond($request, true, 'Oglas je sačuvan u omiljenim.');
    }

    public function destroy(Request $request, int $listing)
    {
        $this->favorites->remove($request->user(), $listing);

        return $this->respond($request, false, 'Oglas je uklonjen iz omiljenih.');
    }

    private function respond(Request $request, bool $saved, string $message, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['favorite' => $saved, 'message' => $message], $status);
        }

        $back = url()->previous();
        if ($back === url()->current() || ! str_starts_with($back, url('/'))) {
            $back = url_slash('moj-nalog/omiljeni');
        }

        return redirect()->to($back)->with('status', $message);
    }
}
