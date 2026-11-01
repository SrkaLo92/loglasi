<?php

namespace App\Http\Controllers\Info;

use App\Http\Controllers\Controller;
use App\Models\InfoPost;
use App\Support\Info\InfoUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** "Pošalji vest": citizens and firms send a news item, event or notice; it waits for the editor. */
class SubmitController extends Controller
{
    public function form(): View
    {
        return view('info.submit');
    }

    public function store(Request $request): RedirectResponse
    {
        if (filled($request->input('website_url'))) { // honeypot
            return redirect()->to(InfoUrl::to('posalji-vest'));
        }
        $data = $request->validate([
            'type' => ['required', 'in:event,news,notice'],
            'title' => ['required', 'string', 'min:5', 'max:160'],
            'summary' => ['required', 'string', 'min:20', 'max:3000'],
            'place' => ['nullable', 'string', 'max:80'],
            'event_date' => ['nullable', 'required_if:type,event', 'date_format:Y-m-d'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'event_venue' => ['nullable', 'string', 'max:160'],
            'submitter_name' => ['required', 'string', 'max:120'],
            'submitter_email' => ['required', 'email', 'max:190'],
            'consent' => ['accepted'],
        ], [], [
            'type' => 'vrsta', 'title' => 'naslov', 'summary' => 'tekst', 'place' => 'mesto', 'event_date' => 'datum',
            'event_time' => 'vreme', 'event_venue' => 'mesto održavanja', 'submitter_name' => 'ime', 'submitter_email' => 'mejl',
            'consent' => 'potvrda',
        ]);

        $startsAt = null;
        if ($data['type'] === 'event' && ! empty($data['event_date'])) {
            $startsAt = Carbon::parse($data['event_date'].' '.($data['event_time'] ?? '00:00'), 'Europe/Belgrade');
        }

        InfoPost::query()->create([
            'type' => $data['type'],
            'status' => 'pending',
            'origin' => 'citizen',
            'title' => $data['title'],
            'summary' => $data['summary'],
            'place' => $data['place'] ?? null,
            'event_starts_at' => $startsAt,
            'event_venue' => $data['event_venue'] ?? null,
            'submitter_name' => $data['submitter_name'],
            'submitter_email' => $data['submitter_email'],
        ]);

        return redirect()->to(InfoUrl::to('posalji-vest'))
            ->with('info_status', 'Hvala! Pregledaćemo poruku i objaviti je, obično istog dana.');
    }
}
