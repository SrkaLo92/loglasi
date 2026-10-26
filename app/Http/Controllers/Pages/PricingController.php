<?php

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use App\Support\Pages\PageText;
use App\Support\Pages\Pricing;
use App\Support\Seo;
use Illuminate\View\View;

class PricingController extends Controller
{
    public function __invoke(): View
    {
        // A question tied to a service is not shown when the service is not active.
        $faq = array_values(array_filter(
            PageText::list('cenovnik.pitanja'),
            fn ($item) => is_array($item) && (empty($item['uslov']) || Pricing::product((string) $item['uslov']) !== null),
        ));

        Seo::get()->faq(array_map(fn (array $item) => [
            'q' => PageText::replace((string) $item['pitanje']),
            'a' => strip_tags((string) PageText::inline((string) $item['odgovor'])),
        ], $faq));

        return view('pages.pricing', [
            'faq' => $faq,
            'paymentRows' => Pricing::paymentReady() ? Pricing::paymentRows() : [],
            'title' => PageText::get('seo.cenovnik.title'),
            'description' => PageText::get('seo.cenovnik.description'),
            'canonical' => '/cenovnik/',
        ]);
    }
}
