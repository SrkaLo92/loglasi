<?php

namespace App\Notifications;

use App\Models\Listing;
use App\Models\Report;
use App\Models\ReportMessage;
use App\Models\User;
use App\Notifications\Messages\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Mail to the person who reported: the admin asks for details (the bell and push go through InAppNotifications). */
class ReportQuestionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Report $report, public ReportMessage $message)
    {
        $this->afterCommit();
    }

    public function via(User $notifiable): array
    {
        return $notifiable->hasVerifiedEmail() ? ['mail'] : [];
    }

    public function toMail(User $notifiable): BrandedMailMessage
    {
        $target = $this->report->reportable;
        $what = $target instanceof Listing ? 'oglasa „'.BrandedMailMessage::plain($target->title).'”' : 'naloga';

        return BrandedMailMessage::to($notifiable)
            ->subject('Pitanje o vašoj prijavi')
            ->greeting('Hvala što ste nam javili')
            ->line('Imamo pitanje o vašoj prijavi '.$what.':')
            ->line('„'.BrandedMailMessage::plain(Str::limit($this->message->body, 600)).'”')
            ->line('Odgovorite nam da bismo mogli da rešimo prijavu.')
            ->action('Odgovori', url_slash('moj-nalog/prijave/'.$this->report->id));
    }
}
