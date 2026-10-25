<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Support\Search\CategorySheet;
use Illuminate\Http\JsonResponse;

/**
 * Search index of "Sve kategorije" (every category and group with its count), loaded by cats.js only when
 * someone types in the sheet's search box, instead of ~57 KB inline on every page with the sheet.
 */
class CategoryIndexController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(self::items(), 200, ['Cache-Control' => 'public, max-age=300'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** @return list<array{n: string, p: ?string, u: string, c: int}> */
    public static function items(): array
    {
        return array_map(fn (array $c) => ['n' => $c['name'], 'p' => $c['path'], 'u' => $c['url'], 'c' => $c['count']], CategorySheet::get()['all']);
    }
}
