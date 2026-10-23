<?php

namespace App\Http\Controllers\Stranice;

use App\Http\Controllers\Controller;
use App\Models\Product;
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

        return view('stranice.cenovnik', [
            'products' => Cenovnik::products(),
            'biznisCode' => Product::BIZNIS_12M,
            'faq' => $faq,
            'paymentRows' => Cenovnik::paymentReady() ? Cenovnik::paymentRows() : [],
            'title' => 'Cenovnik: besplatni oglasi i dodatne usluge | loglasi.rs',
            'description' => 'Postavljanje oglasa na loglasi.rs je besplatno. Cene podizanja i isticanja oglasa i Biznis paketa za firme, i kako da platite uplatom na račun.',
            'canonical' => url_slash('cenovnik'),
        ]);
    }
}
