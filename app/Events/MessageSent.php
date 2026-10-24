<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A message was stored (after commit). `$message->conversation` is loaded.
 * The messages module sends nothing itself: email and push to the recipient belong to the
 * notifications module, which listens here (recipient = the participant who is not sender_id).
 */
class MessageSent implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly Message $message) {}
}
