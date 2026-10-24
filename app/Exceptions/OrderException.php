<?php

namespace App\Exceptions;

use RuntimeException;

/** An order cannot be created, cancelled or fulfilled. The message is user-facing (Serbian). */
class OrderException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'order_error')
    {
        parent::__construct($message);
    }
}
