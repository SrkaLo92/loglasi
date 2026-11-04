<?php

namespace App\Notifications;

use App\Models\Report;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Messages\PushMessage;
use App\Services\Notifications\InAppNotifications;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Push to staff: the reporter answered the admin's question (app setting "admin_queue"). data: type report_reply, report_id. */
class ReportReplyPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Report $report)
    {
        $this->afterCommit();
    }

    public function via(User $notifiable): array
    {
        return $notifiable->isStaff() && ($notifiable->notificationSettings()['admin_queue'] ?? false) ? [PushChannel::class] : [];
    }

    public function toPush(User $notifiable): PushMessage
    {
        return new PushMessage(
            title: 'Odgovor na pitanje o prijavi',
            body: 'Prijavilac je odgovorio. Pogledajte prepisku u admin panelu, Prijave.',
            data: ['type' => InAppNotifications::REPORT_REPLY, 'report_id' => $this->report->id],
        );
    }
}
