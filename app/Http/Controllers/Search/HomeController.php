<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Support\Home\HomeSections;
use App\Support\Seo;
use Illuminate\Http\Request;

/** Home page: 8 sections of the approved mockup (intro, categories, featured, latest, tabs, firms, why, about). */
class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        // Old WordPress search /?s=...: the listing list with that keyword (200, noindex).
        if (is_string($request->query('s')) && trim($request->query('s')) !== '') {
            return app(ListingListController::class)->legacySearch($request);
        }

        Seo::get()->home();

        return view('home.index', ['sections' => HomeSections::get()]);
    }
}
