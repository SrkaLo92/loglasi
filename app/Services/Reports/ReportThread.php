<?php

namespace App\Services\Reports;

use App\Enums\ReportStatus;
use App\Models\Report;
use App\Models\ReportMessage;
use App\Models\User;
use App\Notifications\ReportQuestionNotification;
use App\Services\Notifications\InAppNotifications;
use DomainException;
use Illuminate\Validation\ValidationException;

/**
 * Talking with the person who reported: the admin asks (bell, push and mail to the reporter),
 * the reporter answers on /moj-nalog/prijave/{id}/ or in the app (the report is marked unread for the staff).
 */
class ReportThread
{
    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 2000;

    public function __construct(private readonly InAppNotifications $inApp) {}

    public function ask(Report $report, User $staff, string $body): ReportMessage
    {
        $reporter = $report->reporter;
        if ($reporter === null || $reporter->trashed()) {
            throw new DomainException('Prijavu je poslao gost ili obrisan nalog, nema kome da se pošalje pitanje.');
        }

        $message = $report->messages()->create(['user_id' => $staff->id, 'from_staff' => true, 'body' => $this->clean($body)]);
        $report->forceFill(['staff_unread' => false])->save();

        $this->inApp->reportQuestion($report);
        try {
            $reporter->notify(new ReportQuestionNotification($report, $message));
        } catch (\Throwable $e) {
            report($e);
        }

        return $message;
    }

    public function reply(Report $report, User $reporter, string $body): ReportMessage
    {
        if ($report->reporter_id !== $reporter->id) {
            abort(404);
        }
        if ($report->status !== ReportStatus::Open) {
            throw ValidationException::withMessages(['body' => 'Prijava je već rešena, odgovor više nije potreban.']);
        }

        $message = $report->messages()->create(['user_id' => $reporter->id, 'from_staff' => false, 'body' => $this->clean($body)]);
        $report->forceFill(['staff_unread' => true])->save();
        $this->inApp->reportReplied($report);

        return $message;
    }

    public function markRead(Report $report): void
    {
        if ($report->staff_unread) {
            $report->forceFill(['staff_unread' => false])->save();
        }
    }

    /** Validation rules of a message (site, API and admin). */
    public static function rules(): array
    {
        return ['required', 'string', 'min:'.self::MIN_LENGTH, 'max:'.self::MAX_LENGTH];
    }

    public static function messages(string $field = 'body'): array
    {
        return [
            $field.'.required' => 'Napišite poruku.',
            $field.'.min' => 'Poruka je prekratka.',
            $field.'.max' => 'Poruka može imati najviše '.number_format(self::MAX_LENGTH, 0, ',', '.').' znakova.',
        ];
    }

    private function clean(string $body): string
    {
        return trim(strip_tags($body));
    }
}
