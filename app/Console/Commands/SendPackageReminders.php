<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Mail\Firms\PackageExpiringSoon;
use App\Models\Firm;
use App\Services\FirmBenefits;
use App\Services\Notifications\InAppNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Daily: queues a reminder mail before a firm's package ends (there is no automatic billing).
 * Yearly terms and the free first year: config firms.reminder_days (14 and 3 days before);
 * monthly terms: firms.reminder_days_monthly (3 days before). Each reminder goes out once per term
 * (package_reminder_days / package_reminder_for), so a renewal starts again and a missed run sends
 * only the closest reminder.
 */
class SendPackageReminders extends Command
{
    protected $signature = 'firms:package-reminders';

    protected $description = 'Queue firm package expiry reminders';

    public function handle(FirmBenefits $benefits): int
    {
        $longest = max(array_map('intval', array_merge((array) config('firms.reminder_days', [14, 3]), (array) config('firms.reminder_days_monthly', [3]))));
        $sent = 0;

        Firm::query()
            ->with('user')
            ->where('package_ends_at', '>', now())
            ->where('package_ends_at', '<=', now()->addDays($longest + 1))
            ->whereHas('user', fn ($q) => $q->where('account_type', AccountType::Firm->value)->where('status', UserStatus::Active->value))
            ->orderBy('id')
            ->chunkById(200, function ($firms) use ($benefits, &$sent) {
                foreach ($firms as $firm) {
                    if (! $benefits->hasPackage($firm)) {
                        continue;
                    }
                    $steps = array_map('intval', (array) config($firm->package_period === 'monthly' && ! $firm->onFreeYear()
                        ? 'firms.reminder_days_monthly' : 'firms.reminder_days'));
                    $left = (int) $benefits->daysLeft($firm);
                    // The smallest reminder step that the remaining days have reached.
                    $due = collect($steps)->filter(fn ($step) => $left <= $step)->min();
                    if ($due === null || $left < 0) {
                        continue;
                    }

                    $sameTerm = $firm->package_reminder_for !== null && $firm->package_reminder_for->equalTo($firm->package_ends_at);
                    if ($sameTerm && $firm->package_reminder_days !== null && (int) $firm->package_reminder_days <= $due) {
                        continue;
                    }

                    Mail::to($firm->user->email, $firm->name)->queue(new PackageExpiringSoon($firm, $left));
                    app(InAppNotifications::class)->packageExpiring((int) $firm->user_id, $left);
                    $firm->forceFill(['package_reminder_days' => $due, 'package_reminder_for' => $firm->package_ends_at])->saveQuietly();
                    $sent++;
                }
            });

        $this->info("Reminders queued: {$sent}");

        return self::SUCCESS;
    }
}
