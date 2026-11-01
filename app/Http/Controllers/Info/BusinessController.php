<?php

namespace App\Http\Controllers\Info;

use App\Http\Controllers\Controller;
use App\Models\InfoBusiness;
use App\Models\InfoBusinessRequest;
use App\Support\Info\InfoAds;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Business directory on the info portal ("Firme iz Loznice i okoline"). */
class BusinessController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $category = trim((string) $request->query('kategorija', ''));

        $businesses = InfoBusiness::query()->listed()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('category', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->with('firm')->directoryOrder()
            ->paginate(30)->withQueryString();

        $categories = InfoBusiness::query()->listed()->whereNotNull('category')
            ->selectRaw('category, count(*) as total')->groupBy('category')->orderByDesc('total')->limit(40)->pluck('total', 'category');

        return view('info.businesses', compact('businesses', 'categories', 'q', 'category') + [
            'total' => InfoBusiness::query()->listed()->count(),
            'categoryAd' => $category !== '' ? InfoAds::for('category_top', $category) : null,
            'noindex' => $request->query() !== [],
        ]);
    }

    public function show(string $slug): View
    {
        $business = InfoBusiness::query()->listed()->where('slug', $slug)->with('firm.user')->firstOrFail();

        return view('info.business', ['business' => $business]);
    }

    public function request(Request $request, string $slug): RedirectResponse
    {
        $business = InfoBusiness::query()->listed()->where('slug', $slug)->firstOrFail();
        if (filled($request->input('website_url'))) { // honeypot
            return redirect()->to($business->url());
        }
        $data = $request->validate([
            'kind' => ['required', 'in:'.implode(',', array_keys(InfoBusinessRequest::KINDS))],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [], ['kind' => 'zahtev', 'name' => 'ime', 'email' => 'mejl', 'phone' => 'telefon', 'message' => 'poruka']);

        $business->requests()->create($data);

        return redirect()->to($business->url().'#zahtev')
            ->with('info_status', 'Hvala. Javićemo vam se na '.$data['email'].' u roku od jednog radnog dana.');
    }
}
