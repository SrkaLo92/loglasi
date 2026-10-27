<?php

namespace App\Http\Controllers\Messages;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messages\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use App\Services\Messages\MessageBody;
use App\Services\Messages\MessageRejected;
use App\Services\Messages\Messenger;
use App\Services\Ratings\SellerRatings;
use App\Support\Listing\ReportReasons;
use App\Support\Listing\Seller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Starting a conversation from a listing, the conversation view, replies and polling. */
class ConversationController extends Controller
{
    public function __construct(private Messenger $messenger) {}

    /** /moj-nalog/poruke/novo/?oglas={id} */
    public function create(Request $request)
    {
        // Old profile links (?korisnik={id}): a conversation is always about a listing, so use the newest active one.
        if ($request->query('oglas') === null && $request->query('korisnik') !== null) {
            return $this->fromProfile($request->query('korisnik'));
        }

        $listing = $this->listing($request->query('oglas'));
        $user = $request->user();

        if ($listing->user_id === $user->id) {
            return redirect()->to($listing->url())->with('status', MessageRejected::ownListing()->getMessage());
        }

        $existing = Conversation::query()->where('listing_id', $listing->id)->where('buyer_id', $user->id)->first();
        if ($existing) {
            return redirect()->to(url_slash('moj-nalog/poruke/'.$existing->id));
        }

        abort_unless($this->messenger->acceptsMessages($listing), 404);

        $listing->load(['user:id,name,account_type', 'coverImage']);

        return view('messages.create', [
            'listing' => $listing,
            'thumb' => $listing->coverImage?->sizeUrl('sm'),
        ]);
    }

    public function store(SendMessageRequest $request)
    {
        $listing = $this->listing($request->input('oglas'));

        try {
            $message = $this->messenger->start($request->user(), $listing, $request->body());
        } catch (MessageRejected $e) {
            abort_if($e->status === 404, 404);

            return back()->withInput($request->only('body'))->withErrors(['body' => $e->getMessage()]);
        }

        return redirect()->to(url_slash('moj-nalog/poruke/'.$message->conversation_id).'#poruka-'.$message->id)
            ->with('status', 'Poruka je poslata.');
    }

    public function show(Request $request, Conversation $conversation)
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $before = filter_var($request->query('before'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $page = $this->messenger->messages($conversation, $before, (int) config('messages.messages_per_page'));

        $this->messenger->markRead($user, $conversation);

        $conversation->load([
            'buyer:id,name,slug,account_type,status,deleted_at',
            'seller:id,name,slug,account_type,status,deleted_at',
            'listing' => fn ($q) => $q->withTrashed()->with('coverImage'),
        ]);

        $other = $conversation->otherUser($user);
        $otherId = $conversation->otherId($user);
        $iBlocked = $this->messenger->hasBlocked($user->id, $otherId);
        $blockedMe = $this->messenger->hasBlocked($otherId, $user->id);

        return view('messages.show', [
            'iBlocked' => $iBlocked,
            'blockedMe' => $blockedMe,
            'user' => $user,
            'conversation' => $conversation,
            'other' => $other,
            'otherAvailable' => $other && ! $other->trashed() && ! $other->isBlocked(),
            'listing' => $conversation->listing,
            'thumb' => $conversation->listing?->coverImage?->sizeUrl('sm'),
            'messages' => $page['items'],
            'hasOlder' => $page['has_older'],
            'otherReadAt' => $conversation->otherReadAt($user),
            'rating' => app(SellerRatings::class)->card($user, $conversation),
        ]);
    }

    public function reply(SendMessageRequest $request, Conversation $conversation)
    {
        Gate::authorize('reply', $conversation);

        try {
            $message = $this->messenger->reply($request->user(), $conversation, $request->body());
        } catch (MessageRejected $e) {
            return back()->withInput($request->only('body'))->withErrors(['body' => $e->getMessage()]);
        }

        return redirect()->to(url_slash('moj-nalog/poruke/'.$conversation->id).'#poruka-'.$message->id);
    }

    /** JSON for the page script: messages after ?after={id}, rendered as HTML, and the read receipt. */
    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $user = $request->user();
        $after = max(0, (int) $request->query('after'));
        $messages = $this->messenger->newerThan($conversation, $after, 50);

        if ($messages->contains(fn (Message $m) => (int) $m->sender_id !== $user->id)) {
            $this->messenger->markRead($user, $conversation);
        }

        $otherReadAt = $conversation->otherReadAt($user);

        return response()->json([
            'html' => $messages->map(fn (Message $m) => view('messages.partials.message', [
                'message' => $m,
                'mine' => (int) $m->sender_id === $user->id,
            ])->render())->implode(''),
            'last_id' => (int) ($messages->last()?->id ?? $after),
            'read_until' => $otherReadAt?->toIso8601String(),
        ], 200, ['Cache-Control' => 'private, no-store']);
    }

    /** Block the other participant (POST) or lift the block (DELETE). */
    public function block(Request $request, Conversation $conversation)
    {
        Gate::authorize('view', $conversation);
        $otherId = $conversation->otherId($request->user());

        if ($request->isMethod('delete')) {
            $this->messenger->unblock($request->user(), $otherId);
            $status = 'Korisnik je odblokiran.';
        } else {
            $this->messenger->block($request->user(), $otherId);
            $status = 'Korisnik je blokiran. Više ne možete da razmenjujete poruke.';
        }

        return redirect()->to(url_slash('moj-nalog/poruke/'.$conversation->id))->with('status', $status);
    }

    /** Report the other participant to the moderators; the report points to the user and names the conversation. */
    public function report(Request $request, Conversation $conversation)
    {
        Gate::authorize('view', $conversation);

        $data = $request->validateWithBag('report', [
            'reason' => ['required', Rule::in(ReportReasons::keys())],
            'details' => ['nullable', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Izaberite razlog prijave.',
            'reason.in' => 'Izaberite razlog prijave.',
            'details.max' => 'Opis može imati najviše 1.000 znakova.',
        ]);

        $user = $request->user();
        $other = User::withTrashed()->find($conversation->otherId($user));
        abort_unless($other, 404);

        $details = trim(MessageBody::clean($data['details'] ?? ''));
        Report::fileOnce($user, $other, $data['reason'], 'Razgovor #'.$conversation->id.($details !== '' ? ': '.$details : ''));

        return redirect()->to(url_slash('moj-nalog/poruke/'.$conversation->id))->with('status', 'Hvala, prijava je poslata moderatorima.');
    }

    private function fromProfile(mixed $userId)
    {
        abort_unless(is_numeric($userId) && (int) $userId > 0, 404);
        $listingId = Seller::latestListingId((int) $userId);

        if ($listingId === null) {
            return redirect()->to(url_slash('moj-nalog/poruke'))
                ->with('status', 'Ovaj korisnik trenutno nema aktivnih oglasa, pa mu ne možete poslati poruku.');
        }

        return redirect()->to(url_slash('moj-nalog/poruke/novo').'?oglas='.$listingId);
    }

    private function listing(mixed $id): Listing
    {
        abort_unless(is_numeric($id) && (int) $id > 0, 404);

        return Listing::query()->findOrFail((int) $id);
    }
}
