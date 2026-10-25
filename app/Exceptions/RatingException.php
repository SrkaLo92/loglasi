<?php

namespace App\Exceptions;

use RuntimeException;

/** A rating step that is not allowed; the message is shown to the user, the code is for the API. */
class RatingException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
