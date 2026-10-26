<?php

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use App\Support\Pages\PageText;
use App\Support\Pages\Pricing;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\View\View;

class ForFirmsController extends Controller
{
    public function __invoke(): View
    {
        $phone = (string) Settings::get('contact_phone', '');

        return view('pages.for-firms', [
            'packages' => Pricing::packages(),
            'counter' => Pricing::counter(),
            'offerOpen' => Pricing::offerOpen(),
            'offer' => Pricing::offerText(),
            'freeListings' => Pricing::freeListings(),
            'phone' => Phone::normalize($phone),
            'phoneText' => Phone::display($phone),
            'title' => PageText::get('seo.za_firme.title'),
            'description' => PageText::get('seo.za_firme.description'),
            'canonical' => '/za-firme/',
        ]);
    }
}
