<?php

namespace App\Http\Controllers\Messages;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Messages\Messenger;
use Illuminate\Http\Request;

/** /moj-nalog/poruke/: conversations grouped by listing, newest activity first. */
class InboxController extends Controller
{
    public function __construct(private Messenger $messenger) {}

    public function index(Request $request)
    {
        // Old link from the listing page on the current site: /moj-nalog/poruke/?korisnik={user}&oglas={listing}
        if ($request->filled('oglas')) {
            return redirect()->to(url_slash('moj-nalog/poruke/novo').'?'.http_build_query(['oglas' => (int) $request->query('oglas')]));
        }

        $user = $request->user();
        $page = $this->messenger->inbox($user, $this->cursor($request), (int) config('messages.inbox_per_page'));
        $page['items']->load(['listing' => fn ($q) => $q->withTrashed()->select('id', 'title', 'slug', 'status', 'price', 'currency', 'expires_at', 'deleted_at')
            ->with('coverImage')]);

        // Group the page by listing, keeping the order of the newest conversation in each group.
        $groups = $page['items']
            ->groupBy(fn (Conversation $c) => $c->listing_id ?? 'none-'.$c->id)
            ->map(fn ($items) => [
                'listing' => $items->first()->listing,
                'thumb' => $items->first()->listing?->coverImage?->sizeUrl('sm'),
                'conversations' => $items,
            ])
            ->values();

        return view('messages.inbox', [
            'user' => $user,
            'groups' => $groups,
            'next' => $page['next'],
            'isFirstPage' => $this->cursor($request) === null,
            'unreadTotal' => $this->messenger->unreadTotal($user),
        ]);
    }

    private function cursor(Request $request): ?string
    {
        $cursor = $request->query('cursor');

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }
}
