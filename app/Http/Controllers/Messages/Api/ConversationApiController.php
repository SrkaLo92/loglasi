<?php

namespace App\Http\Controllers\Messages\Api;

use App\Http\Api\ApiError;
use App\Http\Api\ApiResponse;
use App\Http\Api\ListingFormatter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Messages\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\User;
use App\Services\Messages\MessageRejected;
use App\Services\Messages\Messenger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** GET/POST /conversations, GET/POST /conversations/{id}/messages */
class ConversationApiController extends Controller
{
    public function __construct(private Messenger $messenger) {}

    /** Newest first. `page` per contract; `cursor` (from the X-Next-Cursor header) for keyset paging. */
    public function index(Request $request)
    {
        $query = $this->query($request, [
            'page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'cursor' => ['nullable', 'string', 'max:64'],
        ], [
            'page' => 'Stranica mora biti od 1 do 50.',
            'per_page' => 'Broj razgovora po stranici mora biti od 1 do 50.',
            'cursor' => 'Neispravan kursor.',
        ]);

        $user = $request->user();
        $page = $this->messenger->inbox($user, $query['cursor'] ?? null, (int) ($query['per_page'] ?? config('messages.inbox_per_page')), (int) ($query['page'] ?? 1));
        $page['items']->load(['listing' => fn ($q) => $q->withTrashed()->with(ListingFormatter::CARD_RELATIONS)]);

        $data = $page['items']->map(fn (Conversation $c) => MessagesPresenter::conversation($c, $user))->values();

        return ApiResponse::json($request, $data, ApiResponse::CACHE_PRIVATE, 200, $page['next'] ? ['X-Next-Cursor' => $page['next']] : []);
    }

    /** First message to the seller; returns the existing conversation when there is one. */
    public function store(SendMessageRequest $request)
    {
        $listingId = $request->input('listing_id');
        if (! is_numeric($listingId) || (int) $listingId < 1) {
            throw new ApiError('validation_failed', 'Izaberite oglas.', 422, ['listing_id' => 'Izaberite oglas.']);
        }

        $listing = Listing::query()->find((int) $listingId) ?? throw ApiError::notFound('Oglas ne postoji ili više nije aktivan.');

        $message = $this->send(fn () => $this->messenger->start($request->user(), $listing, $request->body()));

        $conversation = $message->conversation->load(['buyer', 'seller', 'lastMessage', 'listing' => fn ($q) => $q->withTrashed()->with(ListingFormatter::CARD_RELATIONS)]);

        return ApiResponse::json($request, MessagesPresenter::conversation($conversation, $request->user()), null, 201);
    }

    /** Up to 50 messages, newest last; marks the conversation as read. */
    public function messages(Request $request, Conversation $conversation)
    {
        $this->authorizeOr404('view', $conversation);

        $query = $this->query($request, ['before' => ['nullable', 'integer', 'min:1']], ['before' => 'Mora biti pozitivan ceo broj.']);

        $user = $request->user();
        $before = isset($query['before']) ? (int) $query['before'] : null;
        $this->messenger->markRead($user, $conversation);

        // The app polls this while a chat is open: answer 304 from the conversation row alone,
        // without loading the messages, when nothing new was sent or read.
        $etag = self::messagesEtag($conversation, $user, $before);
        if (ApiResponse::matches((string) $request->headers->get('If-None-Match'), $etag)) {
            return response('', 304, ['ETag' => $etag, 'Cache-Control' => ApiResponse::CACHE_PRIVATE]);
        }

        $page = $this->messenger->messages($conversation, $before, (int) config('messages.messages_per_page'));
        $data = $page['items']->map(fn ($m) => MessagesPresenter::message($m, $conversation, $user))->values();

        return ApiResponse::json($request, $data, ApiResponse::CACHE_PRIVATE, 200, ['ETag' => $etag]);
    }

    public function reply(SendMessageRequest $request, Conversation $conversation)
    {
        $this->authorizeOr404('reply', $conversation);

        $message = $this->send(fn () => $this->messenger->reply($request->user(), $conversation, $request->body()));

        return ApiResponse::json($request, MessagesPresenter::message($message, $message->conversation, $request->user()), null, 201);
    }

    /**
     * Changes when a message is sent or the other side reads one of the viewer's messages
     * (the "read" flags); after markRead every incoming message is read.
     */
    private static function messagesEtag(Conversation $conversation, User $viewer, ?int $before): string
    {
        $last = $conversation->last_message_at;
        $otherRead = $conversation->otherReadAt($viewer);
        $readState = $otherRead === null ? '-' : ($last !== null && $otherRead->greaterThan($last) ? 'all' : $otherRead->getTimestamp());

        return 'W/"m'.md5(implode('|', [
            $conversation->id, $viewer->id, $before ?? 0, (int) config('messages.messages_per_page'), $last?->getTimestamp() ?? 0, $readState,
        ])).'"';
    }

    private function send(callable $action)
    {
        try {
            return $action();
        } catch (MessageRejected $e) {
            throw new ApiError($e->reason, $e->getMessage(), $e->status);
        }
    }

    private function authorizeOr404(string $ability, Conversation $conversation): void
    {
        if (! Gate::allows($ability, $conversation)) {
            throw ApiError::notFound('Razgovor ne postoji.');
        }
    }

    /** @return array<string, mixed> */
    private function query(Request $request, array $rules, array $messages): array
    {
        $validator = Validator::make($request->query(), $rules);
        if ($validator->fails()) {
            throw ApiError::invalidParams(array_intersect_key($messages, $validator->errors()->toArray()));
        }

        return $validator->validated();
    }
}
