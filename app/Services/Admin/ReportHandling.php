<?php

namespace App\Services\Admin;

use App\Enums\ReportStatus;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;

/** Closing reports (resolved / dismissed). Quick actions on the target go through the other admin services. */
class ReportHandling
{
    /** Report reasons from the API contract (POST /reports). */
    public const REASONS = [
        'prevara' => 'Prevara',
        'pogresna_kategorija' => 'Pogrešna kategorija',
        'duplikat' => 'Duplikat',
        'neprikladno' => 'Neprikladan sadržaj',
        'prodato' => 'Prodato',
        'drugo' => 'Drugo',
    ];

    public function resolve(Report $report, ?string $action = null): Report
    {
        return $this->close($report, ReportStatus::Resolved, $action);
    }

    public function dismiss(Report $report): Report
    {
        return $this->close($report, ReportStatus::Dismissed);
    }

    /** Closes every other open report on the same target (after acting on it). */
    public function resolveAllFor(Listing|User $target, ?string $action = null): int
    {
        $count = 0;
        foreach ($target->reports()->where('status', ReportStatus::Open)->get() as $report) {
            $this->resolve($report, $action);
            $count++;
        }

        return $count;
    }

    public static function reasonLabel(?string $reason): string
    {
        return self::REASONS[$reason] ?? (string) $reason;
    }

    private function close(Report $report, ReportStatus $status, ?string $action = null): Report
    {
        $before = $report->status;

        $report->forceFill([
            'status' => $status,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ])->save();

        AuditLog::record($status === ReportStatus::Resolved ? 'report.resolve' : 'report.dismiss', $report, array_filter([
            'status' => [$before?->value, $status->value],
            'action' => $action,
            'target' => $report->reportable_type.'#'.$report->reportable_id,
        ]));

        return $report;
    }
}
