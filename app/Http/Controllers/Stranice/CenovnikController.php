<?php

namespace App\Http\Controllers\Stranice;

use App\Http\Controllers\Controller;
use App\Support\Seo;
use App\Support\Stranice\Cenovnik;
use App\Support\Stranice\PageText;
use Illuminate\View\View;

class CenovnikController extends Controller
{
    public function __invoke(): View
    {
        // Pitanje vezano za uslugu se ne prikazuje kad usluga nije aktivna.
        $faq = array_values(array_filter(
            PageText::list('cenovnik.pitanja'),
            fn ($item) => is_array($item) && (empty($item['uslov']) || Cenovnik::product((string) $item['uslov']) !== null),
        ));

        Seo::get()->faq(array_map(fn (array $item) => [
            'q' => PageText::replace((string) $item['pitanje']),
            'a' => strip_tags((string) PageText::inline((string) $item['odgovor'])),
        ], $faq));

        return view('stranice.cenovnik', [
            'faq' => $faq,
            'paymentRows' => Cenovnik::paymentReady() ? Cenovnik::paymentRows() : [],
            'title' => PageText::get('seo.cenovnik.title'),
            'description' => PageText::get('seo.cenovnik.description'),
            'canonical' => '/cenovnik/',
        ]);
    }
}
