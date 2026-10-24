<?php

namespace App\Policies;

use App\Models\Listing;
use App\Models\Order;
use App\Models\User;

/** Orders: the owner sees and cancels their own; staff see all; only the admin marks paid. */
class OrderPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isStaff();
    }

    public function view(User $actor, Order $order): bool
    {
        return (int) $order->user_id === (int) $actor->getKey() || $actor->isStaff();
    }

    /** Ordering for a listing is only possible for one's own listing. */
    public function create(User $actor, ?Listing $listing = null): bool
    {
        if ($actor->isBlocked()) {
            return false;
        }

        return $listing === null || (int) $listing->user_id === (int) $actor->getKey();
    }

    public function cancel(User $actor, Order $order): bool
    {
        return $order->isPending()
            && ((int) $order->user_id === (int) $actor->getKey() || $actor->isAdmin());
    }

    public function markPaid(User $actor, Order $order): bool
    {
        return $actor->isAdmin();
    }
}
