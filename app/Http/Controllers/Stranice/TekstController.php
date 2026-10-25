<?php

namespace App\Http\Controllers\Stranice;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Support\Seo;
use App\Support\Stranice\Cenovnik;
use App\Support\Stranice\Navigacija;
use App\Support\Stranice\PageText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** O nama, Uslovi korišćenja, Politika privatnosti, pravni tekstovi za plaćanje i Česta pitanja: tekst iz podešavanja. */
class TekstController extends Controller
{
    private const PAGES = [
        'o_nama' => ['putanja' => 'o-nama', 'opis' => 'loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu. Ko smo, šta nudimo i kako da nas kontaktirate.'],
        'uslovi' => ['putanja' => 'uslovi-koriscenja', 'opis' => 'Uslovi korišćenja sajta loglasi.rs: nalog, oglasi, zabranjen sadržaj, plaćene usluge i odgovornost.'],
        'brisanje' => ['putanja' => 'brisanje-licnih-podataka', 'opis' => 'Kako da obrišete nalog i lične podatke sa loglasi.rs: sami iz naloga ili na zahtev, i šta se tačno briše.'],
        'privatnost' => ['putanja' => 'politika-privatnosti', 'opis' => 'Koje podatke o ličnosti prikuplja loglasi.rs, zašto ih koristimo, koliko ih čuvamo i koja su vaša prava.'],
        'placanje' => ['putanja' => 'uslovi-placanja', 'opis' => 'Kako se naručuju i plaćaju promocije oglasa i paketi za firme na loglasi.rs.'],
        'odustanak' => ['putanja' => 'odustanak-od-ugovora', 'opis' => 'Pravo na odustanak od ugovora zaključenog na daljinu i obrazac za odustanak.'],
        'reklamacije' => ['putanja' => 'reklamacije', 'opis' => 'Kako da podnesete reklamaciju za plaćenu uslugu na loglasi.rs i kada vraćamo novac.'],
    ];

    public function show(Request $request): View
    {
        $page = (string) $request->route()->defaults['strana'];
        abort_unless(isset(self::PAGES[$page]), 404);

        $heading = PageText::get($page.'.naslov');
        $meta = [
            'title' => PageText::get('seo.'.$page.'.title') ?: $heading.' | loglasi.rs',
            'description' => PageText::get('seo.'.$page.'.description') ?: self::PAGES[$page]['opis'],
            'canonical' => '/'.trim(self::PAGES[$page]['putanja'], '/').'/',
        ];

        if ($page === 'o_nama') {
            return view('stranice.o-nama', $meta + ['stats' => self::stats()]);
        }

        return view('stranice.tekst', [
            'page' => $page,
            'heading' => $heading,
            'body' => PageText::html($page.'.tekst'),
            'title' => PageText::get('seo.'.$page.'.title') ?: $heading.' | loglasi.rs',
            'description' => PageText::get('seo.'.$page.'.description') ?: self::PAGES[$page]['opis'],
            'canonical' => '/'.trim(self::PAGES[$page]['putanja'], '/').'/',
        ]);
    }

    /**
     * Numbers for the About page (cached 10 minutes): active listings, categories, free listings.
     *
     * @return list<array{value: string, label: string}>
     */
    private static function stats(): array
    {
        $counts = Cache::remember('stranice.o_nama.stats', 600, fn () => [
            'listings' => Listing::query()->where('status', ListingStatus::Published)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            'categories' => count(Navigacija::kategorije()),
        ]);
        $number = fn (int $n) => number_format($n, 0, ',', '.');
        $free = Cenovnik::freeListings();

        return array_values(array_filter([
            $counts['listings'] > 0 ? ['value' => $number($counts['listings']), 'label' => Cenovnik::plural($counts['listings'], 'aktivan oglas', 'aktivna oglasa', 'aktivnih oglasa')] : null,
            $counts['categories'] > 0 ? ['value' => $number($counts['categories']), 'label' => 'kategorija za sve što prodajete'] : null,
            $free > 0 ? ['value' => (string) $free, 'label' => Cenovnik::plural($free, 'besplatan oglas', 'besplatna oglasa', 'besplatnih oglasa').' za privatna lica'] : null,
        ]));
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
            'title' => PageText::get('seo.faq.title'),
            'description' => PageText::get('seo.faq.description'),
            'canonical' => '/faq/',
        ]);
    }
}
