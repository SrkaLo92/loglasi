<?php

namespace App\Http\Controllers\Info;

use App\Http\Controllers\Controller;
use App\Models\InfoBusiness;
use App\Models\InfoPharmacyDuty;
use App\Models\InfoPost;
use App\Models\InfoServiceNotice;
use App\Support\Info\InfoAds;
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
        $latest = InfoPost::query()->published()->whereIn('type', ['news', 'notice', 'guide'])
            ->latest('published_at')->limit(12)->get();
        $featured = InfoPost::query()->published()->where('featured', true)->whereIn('type', ['news', 'notice', 'guide', 'story'])
            ->latest('published_at')->first() ?? $latest->first();
        $rest = $latest->reject(fn (InfoPost $p) => $featured && $p->is($featured))->values();
        $withImage = $rest->filter(fn (InfoPost $p) => $p->imageUrl())->take(3)->values();

        return view('info.home', [
            'today' => InfoToday::get(),
            'featured' => $featured,
            'newest' => $rest->take(5),
            'cards' => $withImage->count() === 3 ? $withImage : ($rest->count() >= 8 ? $rest->slice(5, 3)->values() : $rest->take(3)),
            'story' => InfoPost::query()->published()->where('type', 'story')->latest('published_at')->first(),
            'events' => InfoPost::query()->upcomingEvents()->orderBy('event_starts_at')->limit(4)->get(),
            'listings' => InfoToday::latestListings(4),
            'businesses' => InfoBusiness::query()->listed()->with('firm')->directoryOrder()->limit(3)->get(),
            'categories' => InfoBusiness::query()->listed()->whereNotNull('category')
                ->selectRaw('category, count(*) as total')->groupBy('category')->orderByDesc('total')->limit(7)->pluck('category'),
            'homeAd' => InfoAds::for('home_middle'),
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
        $topic = $request->query('tema');
        $topic = is_string($topic) && isset(config('info.topics')[$topic]) ? $topic : null;
        $upcoming = InfoPost::query()->upcomingEvents()->orderBy('event_starts_at')->limit(80)->get();
        $topics = collect(config('info.topics'))->only($upcoming->pluck('topic')->filter()->unique()->all());
        $list = $topic ? $upcoming->where('topic', $topic)->values() : $upcoming;

        return view('info.events', [
            'featured' => $upcoming->firstWhere('featured', true) ?? $upcoming->first(fn (InfoPost $e) => $e->imageUrl()),
            'events' => $list,
            'topics' => $topics,
            'topic' => $topic,
            'noindex' => $request->query() !== [],
        ]);
    }

    public function services(Request $request): View
    {
        $notices = InfoServiceNotice::query()->current()->orderBy('starts_at')->limit(150)->get()->groupBy('kind');
        $power = $notices->get('power', collect());
        $day = min(2, max(0, (int) $request->query('dan', 0)));
        $date = now('Europe/Belgrade')->startOfDay()->addDays($day);

        return view('info.services', [
            'weather' => Weather::current(),
            'pharmacy' => InfoPharmacyDuty::today(),
            'day' => $day,
            'dayDate' => $date,
            'powerDay' => $power->filter(fn (InfoServiceNotice $n) => $n->starts_at->timezone('Europe/Belgrade')->isSameDay($date))->values(),
            'powerCounts' => collect([0, 1, 2])->map(fn ($d) => $power->filter(fn (InfoServiceNotice $n) => $n->starts_at->timezone('Europe/Belgrade')
                ->isSameDay(now('Europe/Belgrade')->startOfDay()->addDays($d)))->count())->all(),
            'water' => $notices->get('water', collect()),
            'roads' => $notices->get('road', collect())->merge($notices->get('other', collect())),
            'sponsor' => InfoAds::for('today_sponsor'),
            'noindex' => $request->query() !== [],
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
            'today' => InfoToday::get(),
            'newest' => InfoPost::query()->published()->whereKeyNot($post->id)->whereIn('type', ['news', 'notice'])
                ->latest('published_at')->limit(4)->get(),
            'nextEvent' => InfoPost::query()->upcomingEvents()->orderBy('event_starts_at')->first(),
            'ad' => InfoAds::for('article_bottom'),
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
                InfoUrl::to('price'), InfoUrl::to('vodici'), InfoUrl::to('firme'), InfoUrl::to('oglasavanje')];
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
