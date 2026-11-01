<?php

namespace App\Http\Controllers\Info;

use App\Http\Controllers\Controller;
use App\Models\InfoBusiness;
use App\Models\InfoPharmacyDuty;
use App\Models\InfoPost;
use App\Models\InfoServiceNotice;
use App\Support\Info\InfoToday;
use App\Support\Info\InfoUrl;
use App\Support\Info\Weather;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class InfoController extends Controller
{
    /** Section path => [types, title, intro]. */
    private const SECTIONS = [
        'vesti' => [['news', 'notice'], 'Vesti iz Loznice', 'Kratko, našim rečima, uz link ka izvoru.'],
        'price' => [['story'], 'Priče iz Loznice', 'Istorija, ljudi i mesta Loznice i okoline.'],
        'vodici' => [['guide'], 'Vodiči', 'Gde, šta i kako u Loznici i okolini.'],
    ];

    public function home(): View
    {
        $latest = InfoPost::query()->published()->whereIn('type', ['news', 'notice', 'story', 'guide'])
            ->latest('published_at')->limit(9)->get();
        $featured = InfoPost::query()->published()->where('featured', true)->latest('published_at')->first()
            ?? $latest->first();

        return view('info.home', [
            'today' => InfoToday::get(),
            'featured' => $featured,
            'latest' => $latest->reject(fn (InfoPost $p) => $p->is($featured))->take(6),
            'events' => InfoPost::query()->upcomingEvents()->orderBy('event_starts_at')->limit(4)->get(),
            'listings' => InfoToday::latestListings(4),
            'businesses' => InfoBusiness::query()->listed()->where('featured', true)->inRandomOrder()->limit(6)->get(),
            'businessCount' => InfoBusiness::query()->listed()->count(),
        ]);
    }

    public function section(Request $request, string $section): View
    {
        [$types, $title, $intro] = self::SECTIONS[$section];
        $topic = $request->query('tema');
        $topic = is_string($topic) && isset(config('info.topics')[$topic]) ? $topic : null;

        $posts = InfoPost::query()->published()->whereIn('type', $types)
            ->when($topic, fn ($q) => $q->where('topic', $topic))
            ->latest('published_at')->paginate(20)->withQueryString();

        return view('info.section', compact('section', 'title', 'intro', 'posts', 'topic') + [
            'noindex' => $request->query() !== [],
        ]);
    }

    public function events(Request $request): View
    {
        $events = InfoPost::query()->upcomingEvents()->orderBy('event_starts_at')->limit(60)->get()
            ->groupBy(fn (InfoPost $e) => $e->event_starts_at?->toDateString());

        return view('info.events', ['days' => $events, 'noindex' => $request->query() !== []]);
    }

    public function services(): View
    {
        $notices = InfoServiceNotice::query()->current()->orderBy('starts_at')->limit(100)->get()->groupBy('kind');

        return view('info.services', [
            'weather' => Weather::current(),
            'pharmacy' => InfoPharmacyDuty::today(),
            'power' => $notices->get('power', collect()),
            'water' => $notices->get('water', collect()),
            'roads' => $notices->get('road', collect())->merge($notices->get('other', collect())),
            'lastWater' => InfoServiceNotice::query()->where('kind', 'water')->where('published', true)->latest('starts_at')->first(),
        ]);
    }

    public function show(string $section, string $slug): View
    {
        $post = InfoPost::query()->published()->where('slug', $slug)->firstOrFail();
        abort_unless($post->section() === $section, 404);

        $more = InfoPost::query()->published()->whereKeyNot($post->id)
            ->whereIn('type', $post->type === 'event' ? ['event'] : ['news', 'notice', 'story', 'guide'])
            ->latest('published_at')->limit(3)->get();

        return view('info.post', [
            'post' => $post,
            'more' => $more,
            'listings' => InfoToday::latestListings(2),
            'noindex' => $post->isSourceSummary(),
        ]);
    }

    public function robots(): Response
    {
        $body = config('info.indexable')
            ? "User-agent: *\nAllow: /\nDisallow: /posalji-vest/\n\nSitemap: ".InfoUrl::base()."/sitemap.xml\n"
            : "User-agent: *\nDisallow: /\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function sitemap(): Response
    {
        $xml = Cache::remember('info.sitemap', 3600, function () {
            $urls = [InfoUrl::to('/'), InfoUrl::to('vesti'), InfoUrl::to('desavanja'), InfoUrl::to('servisne-informacije'),
                InfoUrl::to('price'), InfoUrl::to('vodici'), InfoUrl::to('firme')];
            InfoPost::query()->published()->whereIn('type', ['event', 'story', 'guide', 'news', 'notice'])
                ->latest('published_at')->limit(5000)->get()
                ->reject(fn (InfoPost $p) => $p->isSourceSummary())
                ->each(function (InfoPost $p) use (&$urls) {
                    $urls[] = $p->url();
                });
            InfoBusiness::query()->listed()->orderBy('id')->limit(10000)->get(['slug'])
                ->each(function (InfoBusiness $b) use (&$urls) {
                    $urls[] = $b->url();
                });

            return view('info.sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
