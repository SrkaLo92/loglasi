<?php

namespace App\Policies;

use App\Models\Firm;
use App\Models\User;
use App\Services\FirmBenefits;

/** Firm self-service: only the firm account itself edits its profile and sees its statistics. */
class FirmPolicy
{
    public function update(User $user, Firm $firm): bool
    {
        return $user->isFirm() && ! $user->isBlocked() && (int) $firm->user_id === (int) $user->id;
    }

    public function viewStats(User $user, Firm $firm): bool
    {
        return $this->update($user, $firm) && app(FirmBenefits::class)->canShowStats($firm);
    }

    public function viewAdvancedStats(User $user, Firm $firm): bool
    {
        return $this->update($user, $firm) && app(FirmBenefits::class)->canShowAdvancedStats($firm);
    }
}
