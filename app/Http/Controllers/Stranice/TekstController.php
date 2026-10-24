<?php

namespace App\Http\Controllers\Stranice;

use App\Http\Controllers\Controller;
use App\Support\Seo;
use App\Support\Stranice\PageText;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** O nama, Uslovi korišćenja, Politika privatnosti i Česta pitanja: tekst iz podešavanja. */
class TekstController extends Controller
{
    private const PAGES = [
        'o_nama' => ['putanja' => 'o-nama', 'opis' => 'loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu. Ko smo, šta nudimo i kako da nas kontaktirate.'],
        'uslovi' => ['putanja' => 'uslovi-koriscenja', 'opis' => 'Uslovi korišćenja sajta loglasi.rs: nalog, oglasi, zabranjen sadržaj, plaćene usluge i odgovornost.'],
        'privatnost' => ['putanja' => 'politika-privatnosti', 'opis' => 'Koje podatke o ličnosti prikuplja loglasi.rs, zašto ih koristimo, koliko ih čuvamo i koja su vaša prava.'],
    ];

    public function show(Request $request): View
    {
        $page = (string) $request->route()->defaults['strana'];
        abort_unless(isset(self::PAGES[$page]), 404);

        $heading = PageText::get($page.'.naslov');

        return view('stranice.tekst', [
            'page' => $page,
            'heading' => $heading,
            'body' => PageText::html($page.'.tekst'),
            'title' => $heading.' | loglasi.rs',
            'description' => self::PAGES[$page]['opis'],
            'canonical' => url_slash(self::PAGES[$page]['putanja']),
        ]);
    }

    public function faq(): View
    {
        $items = array_values(array_filter(
            PageText::list('faq.pitanja'),
            fn ($item) => is_array($item) && ! empty($item['pitanje']) && ! empty($item['odgovor']),
        ));

        // FAQPage schema from the same questions (plain text: Markdown links and variables resolved).
        Seo::get()->faq(array_map(fn (array $item) => [
            'q' => PageText::replace((string) $item['pitanje']),
            'a' => strip_tags((string) PageText::inline((string) $item['odgovor'])),
        ], $items));

        return view('stranice.faq', [
            'items' => $items,
            'title' => 'Česta pitanja | loglasi.rs',
            'description' => 'Odgovori na česta pitanja o postavljanju oglasa, ceni, isticanju, Biznis paketu i bezbednoj kupovini na loglasi.rs.',
            'canonical' => url_slash('faq'),
        ]);
    }
}
