<?php

namespace App\Http\Controllers\Firms;

use App\Http\Controllers\Controller;
use App\Support\Firms\FirmDirectory;
use App\Support\Seo;
use App\Support\Seo\Url;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** /firme/: "Firme iz Loznice", firms with an active Biznis package. */
class DirectoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $place = $request->query('mesto');
        $place = is_string($place) && preg_match('/^[a-z0-9-]{1,80}$/', $place) ? $place : null;
        $keyword = $request->query('keyword');
        $keyword = is_string($keyword) ? mb_substr(trim(strip_tags($keyword)), 0, 80) : '';
        $page = $request->query('page', 1);
        abort_unless(is_scalar($page) && ctype_digit((string) $page) && (int) $page >= 1, 404);

        $result = FirmDirectory::search($place, $keyword, (int) $page, url_slash('firme'));
        $items = $result['items'];
        abort_if((int) $page > 1 && $items->isEmpty(), 404);

        $placeName = $place === null ? null : collect($result['places'])->firstWhere('slug', $place)['name'] ?? null;
        $this->seo($result['total'], $items->items(), ($items->currentPage() - 1) * $items->perPage());

        return view('firms.directory', [
            'firms' => $items,
            'places' => $result['places'],
            'place' => $place,
            'placeName' => $placeName,
            'keyword' => $keyword,
            'total' => $result['total'],
            'filtered' => $place !== null || $keyword !== '',
        ]);
    }

    /** @param list<array<string, mixed>> $rows */
    private function seo(int $total, array $rows, int $offset): void
    {
        $path = (string) config('firms.directory.path', '/firme/');
        $count = $total.' '.($total % 10 === 1 && $total % 100 !== 11 ? 'firma' : (in_array($total % 10, [2, 3, 4], true) && ! in_array($total % 100, [12, 13, 14], true) ? 'firme' : 'firmi'));

        $seo = Seo::get()
            ->title('Firme Loznica – imenik lokalnih firmi | loglasi.rs')
            ->h1('Firme iz Loznice')
            ->description($total > 0
                ? $count.' iz Loznice i okoline na jednom mestu: delatnost, adresa, telefon, radno vreme i oglasi svake firme.'
                : 'Imenik firmi iz Loznice i okoline: delatnost, adresa, telefon, radno vreme i oglasi svake firme.')
            ->canonical($path)
            ->breadcrumbs([['Početna', '/'], ['Firme iz Loznice', $path]]);

        if ($total === 0) {
            $seo->noindex();

            return;
        }

        $elements = [];
        foreach ($rows as $i => $row) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => $offset + $i + 1,
                'item' => array_filter([
                    '@type' => 'LocalBusiness',
                    'name' => $row['name'],
                    'url' => Url::canonical('/korisnik/'.$row['slug'].'/'),
                    'image' => $row['logo'] ? config('seo.base_url').(parse_url($row['logo'], PHP_URL_PATH) ?: '') : null,
                    'description' => $row['activity'] ?: null,
                    'address' => [
                        '@type' => 'PostalAddress',
                        'addressLocality' => $row['location'] ?: 'Loznica',
                        'addressCountry' => 'RS',
                    ],
                ]),
            ];
        }

        $seo->schema(['@type' => 'ItemList', 'name' => 'Firme iz Loznice', 'numberOfItems' => count($elements), 'itemListElement' => $elements]);
    }
}
