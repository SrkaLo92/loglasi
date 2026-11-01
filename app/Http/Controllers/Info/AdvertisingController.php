<?php

namespace App\Http\Controllers\Info;

use App\Http\Controllers\Controller;
use App\Models\InfoAd;
use App\Models\InfoAdInquiry;
use App\Support\Info\InfoUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** "Oglašavanje": price list, inquiry form and the click counter for paid ads. */
class AdvertisingController extends Controller
{
    public function show(): View
    {
        return view('info.advertising');
    }

    public function store(Request $request): RedirectResponse
    {
        if (filled($request->input('website_url'))) { // honeypot
            return redirect()->to(InfoUrl::to('oglasavanje').'#upit');
        }
        $data = $request->validate([
            'company' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:40'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:190'],
            'interest' => ['required', 'in:'.implode(',', array_keys(InfoAdInquiry::INTERESTS))],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [], ['company' => 'naziv firme', 'phone' => 'telefon', 'email' => 'mejl', 'interest' => 'šta vas zanima', 'message' => 'poruka']);

        InfoAdInquiry::query()->create($data);

        return redirect()->to(InfoUrl::to('oglasavanje').'#upit')
            ->with('info_status', 'Hvala! Javićemo vam se u toku jednog radnog dana sa predlogom i cenom.');
    }

    public function click(int $id): RedirectResponse
    {
        $ad = InfoAd::query()->with('business')->findOrFail($id);
        $target = $ad->target();
        abort_unless($target, 404);
        InfoAd::query()->whereKey($ad->id)->increment('clicks');

        return redirect()->away($target);
    }
}
