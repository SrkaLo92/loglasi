<?php

namespace App\Http\Controllers\Messages;

use App\Http\Controllers\Controller;
use App\Http\Requests\Messages\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Services\Messages\ListingThumb;
use App\Services\Messages\MessageRejected;
use App\Services\Messages\Messenger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Starting a conversation from a listing, the conversation view, replies and polling. */
class ConversationController extends Controller
{
    public function __construct(private Messenger $messenger) {}

    /** /moj-nalog/poruke/novo/?oglas={id} */
    public function create(Request $request)
    {
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

        $listing->load(['user:id,name,account_type', 'images' => fn ($q) => $q->select('id', 'listing_id', 'path', 'variants', 'sort')]);

        return view('messages.create', [
            'listing' => $listing,
            'thumb' => ListingThumb::url($listing->images->first()),
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
            'listing' => fn ($q) => $q->withTrashed()->with(['images' => fn ($q) => $q->select('id', 'listing_id', 'path', 'variants', 'sort')]),
        ]);

        $other = $conversation->otherUser($user);

        return view('messages.show', [
            'user' => $user,
            'conversation' => $conversation,
            'other' => $other,
            'otherAvailable' => $other && ! $other->trashed() && ! $other->isBlocked(),
            'listing' => $conversation->listing,
            'thumb' => ListingThumb::url($conversation->listing?->images->first()),
            'messages' => $page['items'],
            'hasOlder' => $page['has_older'],
            'otherReadAt' => $conversation->otherReadAt($user),
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

    private function listing(mixed $id): Listing
    {
        abort_unless(is_numeric($id) && (int) $id > 0, 404);

        return Listing::query()->findOrFail((int) $id);
    }
}
