<?php

namespace App\Http\Controllers\Messages\Api;

use App\Http\Api\ListingFormatter;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

/** Conversation and Message shapes from the contract, seen from $viewer. */
class MessagesPresenter
{
    public static function conversation(Conversation $conversation, User $viewer): array
    {
        $other = $conversation->otherUser($viewer);

        return [
            'id' => (int) $conversation->id,
            'listing' => $conversation->listing ? ListingFormatter::card($conversation->listing) : null,
            'other_user' => [
                'id' => $conversation->otherId($viewer),
                'name' => $other && ! $other->trashed() ? $other->name : 'Obrisan korisnik',
                'type' => $other?->isFirm() ? 'firm' : 'private',
            ],
            'last_message' => $conversation->lastMessage ? static::message($conversation->lastMessage, $conversation, $viewer) : null,
            'unread' => $conversation->unreadFor($viewer),
        ];
    }

    /**
     * read: for my message, the other side opened the conversation after it was sent (strictly later,
     * timestamps have one-second precision, so a same-second view does not claim a read); for theirs, I have.
     */
    public static function message(Message $message, Conversation $conversation, User $viewer): array
    {
        $mine = (int) $message->sender_id === (int) $viewer->id;
        $readAt = $mine ? $conversation->otherReadAt($viewer) : $conversation->readAtFor($viewer);

        return [
            'id' => (int) $message->id,
            'body' => (string) $message->body,
            'mine' => $mine,
            'sent_at' => ListingFormatter::date($message->created_at),
            'read' => $readAt !== null && ($mine ? $readAt->greaterThan($message->created_at) : $readAt->greaterThanOrEqualTo($message->created_at)),
        ];
    }
}
