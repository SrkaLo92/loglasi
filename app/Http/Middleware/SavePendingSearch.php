<?php

namespace App\Http\Middleware;

use App\Support\Notifications\SavedSearches;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * A guest clicked "Sačuvaj pretragu": the search waits in the session. On the first
 * signed-in GET (after login or registration) it is saved and the user lands on
 * /moj-nalog/pretrage/ with "Pretraga je sačuvana".
 */
class SavePendingSearch
{
    public function __construct(private readonly SavedSearches $searches) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || $request->user() === null || ! $request->hasSession()
            || ! $request->session()->has(SavedSearches::PENDING)) {
            return $next($request);
        }

        $pending = (array) $request->session()->pull(SavedSearches::PENDING);
        $list = url_slash('moj-nalog/pretrage');

        try {
            $this->searches->save($request->user(), (array) ($pending['query'] ?? []), $pending['name'] ?? null);
            $flash = ['status', 'Pretraga je sačuvana. Javićemo vam kad stignu novi oglasi.'];
        } catch (ValidationException $e) {
            return redirect()->to($list)->withErrors($e->errors());
        }

        if ($request->is('moj-nalog/pretrage', 'moj-nalog/pretrage/')) {
            $request->session()->now(...$flash);

            return $next($request);
        }

        return redirect()->to($list)->with(...$flash);
    }
}
