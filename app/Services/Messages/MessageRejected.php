<?php

namespace App\Services\Messages;

use RuntimeException;

/** A business rule stopped a message. The site shows the message above the form, the API returns it as an Error. */
class MessageRejected extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly int $status = 403,
    ) {
        parent::__construct($message);
    }

    public static function blocked(): self
    {
        return new self('account_blocked', 'Vaš nalog je blokiran i ne može da šalje poruke.', 403);
    }

    public static function unverified(): self
    {
        return new self('email_not_verified', 'Potvrdite email adresu da biste slali poruke.', 403);
    }

    public static function ownListing(): self
    {
        return new self('own_listing', 'Ne možete poslati poruku na svoj oglas.', 403);
    }

    public static function listingUnavailable(): self
    {
        return new self('not_found', 'Oglas ne postoji ili više nije aktivan.', 404);
    }

    public static function recipientUnavailable(): self
    {
        return new self('recipient_unavailable', 'Ovaj korisnik više ne prima poruke.', 403);
    }

    public static function duplicate(): self
    {
        return new self('too_many_requests', 'Istu poruku ste već poslali većem broju prodavaca. Napišite poruku za ovaj oglas ili pokušajte sutra.', 429);
    }

    public static function tooManyConversations(): self
    {
        return new self('too_many_requests', 'Danas ste započeli mnogo razgovora. Pokušajte ponovo sutra.', 429);
    }
}
