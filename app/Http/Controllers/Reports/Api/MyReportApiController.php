<?php

namespace App\Http\Controllers\Reports\Api;

use App\Enums\ReportStatus;
use App\Http\Api\ApiError;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Report;
use App\Models\ReportMessage;
use App\Models\User;
use App\Services\Reports\ReportThread;
use App\Support\Listing\ReportReasons;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /me/reports/{id}: the user's own report with the thread (opened from the report_question notification).
 * POST /me/reports/{id}/messages {body}: the reporter's answer (201 with the updated report; 422 when the report is closed).
 */
class MyReportApiController extends Controller
{
    public function show(Request $request, int $report): JsonResponse
    {
        return response()->json(['data' => $this->present($this->own($request, $report))]);
    }

    public function reply(Request $request, int $report, ReportThread $thread): JsonResponse
    {
        $item = $this->own($request, $report);
        $data = $request->validate(['body' => ReportThread::rules()], ReportThread::messages());
        $thread->reply($item, $request->user(), $data['body']);

        return response()->json(['data' => $this->present($item->load('messages'))], 201);
    }

    private function own(Request $request, int $id): Report
    {
        $report = Report::query()->with(['reportable', 'messages'])->where('reporter_id', $request->user()->id)->find($id);

        return $report ?? throw ApiError::notFound('Prijava ne postoji.');
    }

    /** @return array<string, mixed> */
    private function present(Report $report): array
    {
        $target = $report->reportable;

        return [
            'id' => $report->id,
            'status' => $report->status->value,
            'is_open' => $report->status === ReportStatus::Open,
            'reason' => $report->reason,
            'reason_label' => ReportReasons::ALL[$report->reason] ?? $report->reason,
            'details' => $report->details,
            'created_at' => $report->created_at?->toIso8601String(),
            'target' => match (true) {
                $target instanceof Listing => ['type' => 'listing', 'id' => $target->id, 'title' => $target->title],
                $target instanceof User => ['type' => 'user', 'id' => $target->id, 'title' => $target->name],
                default => null,
            },
            'messages' => $report->messages->map(fn (ReportMessage $m) => [
                'id' => $m->id,
                'from_staff' => $m->from_staff,
                'body' => $m->body,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
