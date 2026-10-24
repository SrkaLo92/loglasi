<?php

namespace App\Http\Controllers\Stranice;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use App\Support\Stranice\Cenovnik;
use App\Support\Stranice\PageText;
use App\Support\Stranice\Telefon;
use Illuminate\View\View;

class ZaFirmeController extends Controller
{
    public function __invoke(): View
    {
        $phone = (string) Settings::get('contact_phone', '');

        return view('stranice.za-firme', [
            'packages' => Cenovnik::packages(),
            'counter' => Cenovnik::counter(),
            'offerOpen' => Cenovnik::offerOpen(),
            'offer' => Cenovnik::offerText(),
            'freeListings' => Cenovnik::freeListings(),
            'phone' => Telefon::normalize($phone),
            'phoneText' => Telefon::display($phone),
            'title' => PageText::get('seo.za_firme.title'),
            'description' => PageText::get('seo.za_firme.description'),
            'canonical' => '/za-firme/',
        ]);
    }
}
