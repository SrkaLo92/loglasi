<?php

namespace App\Http\Controllers\Info;

use App\Http\Controllers\Controller;
use App\Models\InfoBusiness;
use App\Models\InfoPost;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Search across published posts and the business directory. */
class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 80));
        $like = '%'.addcslashes($q, '%_\\').'%';

        $posts = $q === '' ? collect() : InfoPost::query()->published()
            ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('summary', 'like', $like))
            ->latest('published_at')->limit(20)->get();
        $businesses = $q === '' ? collect() : InfoBusiness::query()->listed()
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('category', 'like', $like))
            ->directoryOrder()->limit(12)->get();

        return view('info.search', compact('q', 'posts', 'businesses') + ['noindex' => true]);
    }
}
