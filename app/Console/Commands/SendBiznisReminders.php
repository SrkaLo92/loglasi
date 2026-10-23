<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Mail\Firms\BiznisExpiringSoon;
use App\Models\Firm;
use App\Services\FirmBenefits;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Daily: queues a reminder mail 14 and 3 days (config firms.reminder_days) before biznis_until.
 * Each reminder is sent once per biznis_until (columns biznis_reminder_days / biznis_reminder_for),
 * so a renewal starts the series again and a missed run catches up with the closest reminder only.
 */
class SendBiznisReminders extends Command
{
    protected $signature = 'firms:biznis-reminders';

    protected $description = 'Queue Biznis package expiry reminders (14 and 3 days before)';

    public function handle(FirmBenefits $benefits): int
    {
        $steps = array_map('intval', (array) config('firms.reminder_days', [14, 3]));
        rsort($steps);
        $sent = 0;

        Firm::query()
            ->with('user')
            ->where('biznis_until', '>', now())
            ->where('biznis_until', '<=', now()->addDays(max($steps) + 1))
            ->whereHas('user', fn ($q) => $q->where('account_type', AccountType::Firm->value)->where('status', UserStatus::Active->value))
            ->orderBy('id')
            ->chunkById(200, function ($firms) use ($benefits, $steps, &$sent) {
                foreach ($firms as $firm) {
                    $left = $benefits->daysLeft($firm);
                    // The smallest reminder step that the remaining days have reached.
                    $due = collect($steps)->filter(fn ($step) => $left <= $step)->min();
                    if ($due === null || $left < 0) {
                        continue;
                    }

                    $for = $firm->biznis_reminder_for ? Carbon::parse($firm->biznis_reminder_for) : null;
                    $sameTerm = $for !== null && $for->equalTo($firm->biznis_until);
                    if ($sameTerm && $firm->biznis_reminder_days !== null && (int) $firm->biznis_reminder_days <= $due) {
                        continue;
                    }

                    Mail::to($firm->user->email, $firm->name)->queue(new BiznisExpiringSoon($firm, $left));
                    $firm->forceFill(['biznis_reminder_days' => $due, 'biznis_reminder_for' => $firm->biznis_until])->saveQuietly();
                    $sent++;
                }
            });

        $this->info("Reminders queued: {$sent}");

        return self::SUCCESS;
    }
}
