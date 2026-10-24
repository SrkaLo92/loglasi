<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Only the buyer and the seller see or answer a conversation; for anyone else it does not exist (404). */
class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): Response
    {
        return $conversation->involves($user) ? Response::allow() : Response::denyAsNotFound();
    }

    public function reply(User $user, Conversation $conversation): Response
    {
        return $this->view($user, $conversation);
    }
}
