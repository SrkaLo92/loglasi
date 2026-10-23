<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A message was stored. `$message->conversation` is loaded. Listeners: the recipient email
 * (messages module); push notifications can listen here too.
 */
class MessageSent
{
    use Dispatchable;

    public function __construct(public readonly Message $message) {}
}
