<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\Reports\ReportThread;
use App\Support\Listing\ReportReasons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** /moj-nalog/prijave/{report}/: the user's own report with the admin's questions and their answers. */
class MyReportController extends Controller
{
    public function show(Request $request, int $report): View
    {
        $item = $this->own($request, $report);

        return view('reports.show', [
            'report' => $item,
            'reasonLabel' => ReportReasons::ALL[$item->reason] ?? $item->reason,
        ]);
    }

    public function reply(Request $request, int $report, ReportThread $thread): RedirectResponse
    {
        $item = $this->own($request, $report);
        $data = $request->validate(['body' => ReportThread::rules()], ReportThread::messages());
        $thread->reply($item, $request->user(), $data['body']);

        return redirect()->to(url_slash('moj-nalog/prijave/'.$item->id).'#odgovor')->with('status', 'Odgovor je poslat. Hvala!');
    }

    private function own(Request $request, int $id): Report
    {
        return Report::query()->with(['reportable', 'messages'])
            ->where('reporter_id', $request->user()->id)->findOrFail($id);
    }
}
