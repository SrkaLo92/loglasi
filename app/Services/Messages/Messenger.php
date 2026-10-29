<?php

namespace App\Services\Messages;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Buyer–seller conversations: starting, replying, read receipts, unread counters and the inbox.
 * Rules: docs/modules/messages.md.
 */
class Messenger
{
    /** First message about a listing; continues the existing conversation when there is one. */
    public function start(User $buyer, Listing $listing, string $body): Message
    {
        $this->ensureSenderAllowed($buyer);

        if ($listing->user_id === $buyer->id) {
            throw MessageRejected::ownListing();
        }

        $existing = Conversation::query()
            ->where('listing_id', $listing->id)
            ->where('buyer_id', $buyer->id)
            ->first();

        if ($existing) {
            return $this->reply($buyer, $existing, $body);
        }

        if (! $this->acceptsMessages($listing)) {
            throw MessageRejected::listingUnavailable();
        }

        $seller = $listing->user;
        $this->ensureRecipientAllowed($buyer, $seller);
        $this->ensureNotDuplicate($buyer, $body, null);

        $started = Conversation::query()
            ->where('buyer_id', $buyer->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        if ($started >= (int) config('messages.new_conversations_per_day')) {
            throw MessageRejected::tooManyConversations();
        }

        // createOrFirst: two parallel first messages end in the same conversation (unique listing_id + buyer_id).
        $conversation = Conversation::query()->createOrFirst(
            ['listing_id' => $listing->id, 'buyer_id' => $buyer->id],
            ['seller_id' => $listing->user_id],
        );

        return $this->store($buyer, $conversation, $body);
    }

    public function reply(User $sender, Conversation $conversation, string $body): Message
    {
        if (! $conversation->involves($sender)) {
            throw MessageRejected::recipientUnavailable();
        }

        $this->ensureSenderAllowed($sender);
        $this->ensureRecipientAllowed($sender, User::query()->find($conversation->otherId($sender)));
        $this->ensureNotDuplicate($sender, $body, $conversation->id);

        return $this->store($sender, $conversation, $body);
    }

    /** Listing that can receive a first message: published, not deleted, not past its end date. */
    public function acceptsMessages(Listing $listing): bool
    {
        return $listing->isActive();
    }

    /** $blocker stops all messages with $blockedId (both directions). Idempotent. */
    public function block(User $blocker, int $blockedId): void
    {
        if ($blocker->id === $blockedId) {
            return;
        }
        DB::table('user_blocks')->insertOrIgnore(['blocker_id' => $blocker->id, 'blocked_id' => $blockedId, 'created_at' => now()]);
    }

    public function unblock(User $blocker, int $blockedId): void
    {
        DB::table('user_blocks')->where('blocker_id', $blocker->id)->where('blocked_id', $blockedId)->delete();
    }

    public function hasBlocked(int $blockerId, int $blockedId): bool
    {
        return DB::table('user_blocks')->where('blocker_id', $blockerId)->where('blocked_id', $blockedId)->exists();
    }

    /** Marks everything in the conversation as read for $user (read receipt + unread counter). */
    public function markRead(User $user, Conversation $conversation): void
    {
        $buyer = $conversation->isBuyer($user);
        $readAt = $buyer ? 'buyer_read_at' : 'seller_read_at';
        $unread = $buyer ? 'buyer_unread' : 'seller_unread';

        $hadUnread = $conversation->{$unread} > 0;
        // Polling an open chat must not write the row each time when everything is already read.
        $read = $conversation->{$readAt};
        if (! $hadUnread && $read !== null && ($conversation->last_message_at === null || $read->greaterThan($conversation->last_message_at))) {
            return;
        }
        $now = now();

        Conversation::query()->whereKey($conversation->id)->update([$readAt => $now, $unread => 0]);
        $conversation->forceFill([$readAt => $now, $unread => 0])->syncOriginal();

        if ($hadUnread) {
            $this->forgetUnread($user->id);
        }
    }

    /** Unread messages across all of $user's conversations (header and bottom nav). Cached, reset on send/read. */
    public function unreadTotal(User $user): int
    {
        return (int) Cache::remember(self::unreadKey($user->id), (int) config('messages.unread_cache_seconds'), fn () => (int) Conversation::query()->where('buyer_id', $user->id)->sum('buyer_unread')
                + (int) Conversation::query()->where('seller_id', $user->id)->sum('seller_unread'));
    }

    public function forgetUnread(int $userId): void
    {
        Cache::forget(self::unreadKey($userId));
    }

    public static function unreadKey(int $userId): string
    {
        return 'messages.unread.'.$userId;
    }

    /**
     * Inbox page, newest activity first, keyset pagination on (last_message_at, id).
     * Two index range scans (buyer side, seller side) merged in PHP, instead of one OR query that cannot use an index.
     *
     * @return array{items: Collection<int, Conversation>, next: ?string}
     */
    public function inbox(User $user, ?string $cursor, int $perPage, int $page = 1): array
    {
        $after = InboxCursor::decode($cursor);
        // Contract `page`: without a cursor, page N needs the first N*perPage rows of each side (small per user).
        $take = $after ? $perPage + 1 : $perPage * max(1, $page) + 1;

        $side = function (string $column) use ($user, $after, $take) {
            return Conversation::query()
                ->where($column, $user->id)
                ->whereNotNull('last_message_at')
                ->when($after, fn ($q) => $q->where(fn ($q) => $q
                    ->where('last_message_at', '<', $after['at'])
                    ->orWhere(fn ($q) => $q->where('last_message_at', $after['at'])->where('id', '<', $after['id']))))
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->limit($take)
                ->get();
        };

        $all = $side('buyer_id')->concat($side('seller_id'))
            ->unique('id')
            ->sortByDesc(fn (Conversation $c) => sprintf('%020d-%020d', $c->last_message_at->getTimestamp(), $c->id))
            ->values();

        $offset = $after ? 0 : $perPage * (max(1, $page) - 1);
        $items = $all->slice($offset, $perPage)->values();
        $hasMore = $all->count() > $offset + $perPage;

        $items->load([
            'buyer:id,name,account_type,status,deleted_at',
            'seller:id,name,account_type,status,deleted_at',
            'lastMessage',
        ]);

        $last = $items->last();

        return [
            'items' => $items,
            'next' => $hasMore && $last ? InboxCursor::encode($last->last_message_at, $last->id) : null,
        ];
    }

    /**
     * Messages of a conversation, oldest to newest; $beforeId loads older ones (keyset on id).
     *
     * @return array{items: Collection<int, Message>, has_older: bool}
     */
    public function messages(Conversation $conversation, ?int $beforeId, int $limit): array
    {
        $rows = Message::query()
            ->where('conversation_id', $conversation->id)
            ->when($beforeId, fn ($q) => $q->where('id', '<', $beforeId))
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->get(['id', 'conversation_id', 'sender_id', 'body', 'created_at']);

        return ['items' => $rows->take($limit)->reverse()->values(), 'has_older' => $rows->count() > $limit];
    }

    /** Messages newer than $afterId (polling). */
    public function newerThan(Conversation $conversation, int $afterId, int $limit): Collection
    {
        return Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'conversation_id', 'sender_id', 'body', 'created_at']);
    }

    private function store(User $sender, Conversation $conversation, string $body): Message
    {
        $buyerSends = $conversation->isBuyer($sender);

        $message = DB::transaction(function () use ($sender, $conversation, $body, $buyerSends) {
            $message = $conversation->messages()->create(['sender_id' => $sender->id, 'body' => $body]);
            $at = $message->created_at;

            // Sender has read everything up to his own message; the other side gets one more unread.
            $changes = $buyerSends
                ? ['buyer_read_at' => $at, 'buyer_unread' => 0, 'seller_unread' => DB::raw('seller_unread + 1')]
                : ['seller_read_at' => $at, 'seller_unread' => 0, 'buyer_unread' => DB::raw('buyer_unread + 1')];
            Conversation::query()->whereKey($conversation->id)->update(['last_message_at' => $at, 'updated_at' => $at] + $changes);

            // Interest in the listing: every buyer message counts in the seller's daily stats.
            if ($buyerSends && $conversation->listing_id) {
                ListingStats::increment((int) $conversation->listing_id, 'messages');
            }

            return $message;
        });

        $conversation->refresh();
        $message->setRelation('conversation', $conversation);

        $this->forgetUnread($sender->id);
        $this->forgetUnread($conversation->otherId($sender));

        MessageSent::dispatch($message);

        return $message;
    }

    private function ensureSenderAllowed(User $sender): void
    {
        if ($sender->isBlocked()) {
            throw MessageRejected::blocked();
        }
        if (! $sender->hasVerifiedEmail()) {
            throw MessageRejected::unverified();
        }
    }

    private function ensureRecipientAllowed(User $sender, ?User $recipient): void
    {
        if ($recipient === null || $recipient->trashed() || $recipient->isBlocked()) {
            throw MessageRejected::recipientUnavailable();
        }
        if ($this->hasBlocked($sender->id, $recipient->id)) {
            throw MessageRejected::youBlocked();
        }
        if ($this->hasBlocked($recipient->id, $sender->id)) {
            throw MessageRejected::blockedByRecipient();
        }
    }

    /**
     * Spam guard: the same text to many different conversations in the window is refused.
     * Uses the (sender_id, created_at) index; the body comparison only runs on that sender's recent rows.
     */
    private function ensureNotDuplicate(User $sender, string $body, ?int $conversationId): void
    {
        $others = Message::query()
            ->where('sender_id', $sender->id)
            ->where('created_at', '>=', now()->subHours((int) config('messages.duplicate_window_hours')))
            ->where('body', $body)
            ->when($conversationId, fn ($q) => $q->where('conversation_id', '!=', $conversationId))
            ->distinct()
            ->count('conversation_id');

        if ($others >= (int) config('messages.duplicate_conversations')) {
            throw MessageRejected::duplicate();
        }
    }
}
