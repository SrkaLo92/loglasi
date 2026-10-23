<?php

namespace App\Support;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use App\Support\Seo\Url;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SEO podaci jedne strane: title, opis, canonical, robots, Open Graph, JSON-LD.
 * Jedna instanca po zahtevu; kontroler je popuni, `partials/seo-head` je ispiše.
 *
 *   Seo::for($listing);                      // oglas, kategorija, firma, profil
 *   Seo::for($category, ['location' => $mesto, 'count' => 12]);
 *   Seo::get()->page('Česta pitanja', 'Odgovori na ...')->faq($pitanja);
 *   Seo::get()->title('...')->description('...')->noindex();
 *
 * Pravila za noindex i canonical iz upita (filteri, paginacija) i za strane naloga
 * primenjuju se sama, u resolve(). Opis: docs/moduli/seo.md
 */
class Seo
{
    private ?string $title = null;

    private ?string $description = null;

    private ?string $h1 = null;

    private ?string $canonical = null;

    private bool $withoutCanonical = false;

    private ?bool $indexable = null;

    private string $ogType = 'website';

    /** @var list<string> */
    private array $images = [];

    /** @var list<array{name: string, url: ?string}> */
    private array $breadcrumbs = [];

    /** @var list<array<string, mixed>> */
    private array $schemas = [];

    /** @var array<string, string> dodatni og:/product: meta tagovi */
    private array $extraMeta = [];

    public static function get(): self
    {
        return app(self::class);
    }

    /** Popunjava stranu prema modelu: Listing, Category (+ mesto), User (firma ili privatni profil), Firm. */
    public static function for(Model $model, array $context = []): self
    {
        $seo = static::get();

        return match (true) {
            $model instanceof Listing => $seo->listing($model),
            $model instanceof Category => $seo->category($model, $context['location'] ?? null, $context['count'] ?? null),
            $model instanceof Firm => $seo->firm($model->user ?? new User, $context['activity'] ?? null),
            $model instanceof User => $model->isFirm()
                ? $seo->firm($model, $context['activity'] ?? null)
                : $seo->profile($model),
            default => $seo,
        };
    }

    // ---- osnovni podaci ----

    public function title(?string $title): self
    {
        $this->title = $title === null ? null : static::clean($title);

        return $this;
    }

    public function description(?string $description): self
    {
        $this->description = $description === null ? null : static::excerpt($description, (int) config('seo.description_max', 155));

        return $this;
    }

    public function h1(?string $h1): self
    {
        $this->h1 = $h1;

        return $this;
    }

    /** Putanja (/oglasi/x/) ili apsolutni URL; domen je uvek kanonski. */
    public function canonical(?string $pathOrUrl): self
    {
        $this->canonical = $pathOrUrl;
        $this->withoutCanonical = false;

        return $this;
    }

    public function withoutCanonical(): self
    {
        $this->withoutCanonical = true;

        return $this;
    }

    public function noindex(): self
    {
        $this->indexable = false;

        return $this;
    }

    public function index(): self
    {
        $this->indexable = true;

        return $this;
    }

    /** Open Graph tip: website, product, profile, article. */
    public function type(string $type): self
    {
        $this->ogType = $type;

        return $this;
    }

    /** @param string|list<string> $urls */
    public function image(string|array $urls): self
    {
        foreach ((array) $urls as $url) {
            if ($url) {
                $this->images[] = static::absolute($url);
            }
        }

        return $this;
    }

    /** @param list<array{0: string, 1?: ?string}|array{name: string, url?: ?string}> $items [naziv, putanja] */
    public function breadcrumbs(array $items): self
    {
        $this->breadcrumbs = [];

        foreach ($items as $item) {
            $this->breadcrumb($item['name'] ?? $item[0], $item['url'] ?? $item[1] ?? null);
        }

        return $this;
    }

    public function breadcrumb(string $name, ?string $path = null): self
    {
        $this->breadcrumbs[] = ['name' => $name, 'url' => $path];

        return $this;
    }

    /** Dodatni JSON-LD objekat (bez @context). */
    public function schema(array $schema): self
    {
        $this->schemas[] = $schema;

        return $this;
    }

    /** FAQPage schema. @param list<array{q: string, a: string}|array{question: string, answer: string}> $items */
    public function faq(array $items): self
    {
        $entities = [];

        foreach ($items as $item) {
            $question = $item['q'] ?? $item['question'] ?? null;
            $answer = $item['a'] ?? $item['answer'] ?? null;

            if ($question && $answer) {
                $entities[] = [
                    '@type' => 'Question',
                    'name' => static::clean($question),
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => static::clean($answer)],
                ];
            }
        }

        return $entities === [] ? $this : $this->schema(['@type' => 'FAQPage', 'mainEntity' => $entities]);
    }

    public function meta(string $property, string $content): self
    {
        $this->extraMeta[$property] = $content;

        return $this;
    }

    // ---- gotovi šabloni (keyword mapa) ----

    public function home(): self
    {
        return $this->title('Oglasi Loznica – besplatni mali oglasi | loglasi.rs')
            ->h1('Oglasi Loznica i Podrinje')
            ->description('Kupi i prodaj u Loznici: automobili, stanovi, posao, drva, poljoprivreda. Besplatno objavi oglas.')
            ->canonical('/');
    }

    /** Obična strana (O nama, FAQ, Kontakt...). */
    public function page(string $name, ?string $description = null, ?string $path = null): self
    {
        $this->title(static::fitTitle($name))->h1($name)->description($description ?? config('seo.tagline'));

        if ($path !== null) {
            $this->canonical($path);
        }

        return $this->breadcrumbs([['Početna', '/'], [$name, $path]]);
    }

    /** /oglasi/ (svi oglasi). */
    public function listings(?int $count = null): self
    {
        $count ??= static::activeListings()->count();

        $this->title('Svi oglasi u Loznici'.config('seo.title_suffix'))
            ->h1('Svi oglasi u Loznici')
            ->description(static::countLabel($count).' iz Loznice i okoline: automobili, stanovi, posao, drva, poljoprivreda. Besplatno objavi oglas.')
            ->canonical('/oglasi/')
            ->breadcrumbs([['Početna', '/'], ['Oglasi', '/oglasi/']]);

        return $count === 0 ? $this->noindex() : $this;
    }

    public function listing(Listing $listing): self
    {
        $listing->loadMissing(['category.parent', 'location', 'user.firm', 'images']);
        $place = $listing->location?->name;
        $price = $listing->priceLabel();

        $this->h1($listing->title)
            ->title(static::fitTitle($listing->title.($place ? ', '.$place : '').' – '.$price))
            ->canonical('/oglas/'.$listing->slug.'/')
            ->type('product');

        $text = static::excerpt((string) $listing->description, 140);
        $this->description($text !== ''
            ? $text.($place ? ' ('.$place.')' : '')
            : $listing->title.($place ? ', '.$place : '').'. Cena: '.$price.'. Pogledaj oglas na loglasi.rs.');

        $images = $listing->images->take(6)->map(fn ($image) => static::imageUrl($image->path))->filter()->values()->all();
        $this->image($images);

        $active = static::isActive($listing);

        if (! $active) {
            $this->noindex();
        }

        $crumbs = [['Početna', '/'], ['Oglasi', '/oglasi/']];

        if ($category = $listing->category) {
            if ($category->parent) {
                $crumbs[] = [$category->parent->name, '/oglasi/'.$category->parent->slug.'/'];
            }
            $crumbs[] = [$category->name, '/oglasi/'.$category->slug.'/'];
        }

        $crumbs[] = [$listing->title, null];
        $this->breadcrumbs($crumbs);

        $product = [
            '@type' => 'Product',
            'name' => $listing->title,
            'description' => static::excerpt((string) $listing->description, 500) ?: $listing->title,
            'url' => Url::canonical('/oglas/'.$listing->slug.'/'),
            'sku' => (string) $listing->id,
        ];

        if ($images !== []) {
            $product['image'] = $images;
        }

        if ($listing->category) {
            $product['category'] = $listing->category->name;
        }

        if ($listing->price !== null) {
            $offer = [
                '@type' => 'Offer',
                'price' => static::number((float) $listing->price),
                'priceCurrency' => $listing->currency->value,
                'availability' => $active ? 'https://schema.org/InStock' : 'https://schema.org/Discontinued',
                'url' => Url::canonical('/oglas/'.$listing->slug.'/'),
            ];

            if ($listing->user) {
                $offer['seller'] = [
                    '@type' => $listing->user->isFirm() ? 'Organization' : 'Person',
                    'name' => $listing->user->firm?->name ?? $listing->user->name,
                ];
            }

            if ($place) {
                $offer['availableAtOrFrom'] = [
                    '@type' => 'Place',
                    'address' => ['@type' => 'PostalAddress', 'addressLocality' => $place, 'addressCountry' => 'RS'],
                ];
            }

            $product['offers'] = $offer;
            $this->meta('product:price:amount', static::number((float) $listing->price))
                ->meta('product:price:currency', $listing->currency->value);
        }

        return $this->schema($product);
    }

    /** /oglasi/{kategorija}/ i /oglasi/{kategorija}/{mesto}/ */
    public function category(Category $category, ?Location $location = null, ?int $count = null): self
    {
        $count ??= static::categoryCount($category, $location);
        [$phrase, $accusative] = static::phrase($category);
        $path = '/oglasi/'.$category->slug.'/'.($location ? $location->slug.'/' : '');

        $crumbs = [['Početna', '/'], ['Oglasi', '/oglasi/']];
        $category->loadMissing('parent');

        if ($category->parent) {
            $crumbs[] = [$category->parent->name, '/oglasi/'.$category->parent->slug.'/'];
        }

        $crumbs[] = [$category->name, '/oglasi/'.$category->slug.'/'];

        if ($location) {
            $where = mb_strtolower($location->slug) === 'loznica' ? 'u Loznici' : 'u mestu '.$location->name.', opština Loznica';

            $this->title(static::fitTitle($phrase.' '.$location->name.' – oglasi'))
                ->h1($phrase.' – '.$location->name)
                ->description(static::countLabel($count).' '.static::about($accusative, $category).' '.$where.'.');

            $crumbs[] = [$location->name, $path];
            $indexable = $count >= static::placeMinListings();
        } else {
            $title = $count > 0 ? $phrase.' Loznica – '.static::countLabel($count) : $phrase.' Loznica';

            $this->title($category->seo_title ?: static::fitTitle($title, $phrase.' Loznica'))
                ->h1($phrase.' u Loznici i okolini')
                ->description($category->seo_description ?: static::countLabel($count).' '.static::about($accusative, $category)
                    .' u Loznici, Banji Koviljači i Lešnici. Pogledaj cene ili besplatno objavi oglas.');

            $indexable = $count > 0;
        }

        $this->canonical($path)->breadcrumbs($crumbs);

        return $indexable ? $this : $this->noindex();
    }

    /** Profil firme (/korisnik/{slug}/): indeksabilan uz aktivan Biznis paket, LocalBusiness schema. */
    public function firm(User $user, ?string $activity = null): self
    {
        $user->loadMissing('firm.location');
        $firm = $user->firm;
        $name = $firm?->name ?: $user->name;
        $path = '/korisnik/'.$user->slug.'/';

        $this->h1($name)
            ->title(static::fitTitle($name.' – '.($activity ? $activity.' Loznica' : 'firma iz Loznice')))
            ->description($activity
                ? $activity.' u Loznici: adresa, telefon i oglasi firme '.$name.'.'
                : $name.' iz Loznice: adresa, telefon i oglasi na loglasi.rs.')
            ->canonical($path)
            ->type('profile')
            ->breadcrumbs([['Početna', '/'], [$name, $path]]);

        if ($firm?->logo_path) {
            $this->image(static::imageUrl($firm->logo_path));
        }

        if (! $firm || ! $firm->hasBiznis()) {
            return $this->noindex();
        }

        $business = [
            '@type' => 'LocalBusiness',
            'name' => $name,
            'url' => Url::canonical($path),
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $firm->address,
                'addressLocality' => $firm->location?->name ?? 'Loznica',
                'addressCountry' => 'RS',
            ]),
        ];

        if ($firm->description) {
            $business['description'] = static::excerpt($firm->description, 300);
        }

        if ($user->phone) {
            $business['telephone'] = $user->phone;
        }

        if ($firm->logo_path) {
            $business['image'] = static::imageUrl($firm->logo_path);
            $business['logo'] = $business['image'];
        }

        if ($firm->lat && $firm->lng) {
            $business['geo'] = ['@type' => 'GeoCoordinates', 'latitude' => $firm->lat, 'longitude' => $firm->lng];
        }

        $sameAs = array_values(array_filter([$firm->website, $firm->facebook, $firm->instagram]));

        if ($sameAs !== []) {
            $business['sameAs'] = $sameAs;
        }

        if ($hours = static::openingHours($firm->working_hours)) {
            $business['openingHoursSpecification'] = $hours;
        }

        return $this->schema($business);
    }

    /** Profil privatnog lica: 200, noindex,follow. */
    public function profile(User $user): self
    {
        $path = '/korisnik/'.$user->slug.'/';

        return $this->title(static::fitTitle($user->name.' – oglasi korisnika'))
            ->h1($user->name)
            ->description('Oglasi korisnika '.$user->name.' na loglasi.rs, sajtu za oglase za Loznicu i okolinu.')
            ->canonical($path)
            ->type('profile')
            ->breadcrumbs([['Početna', '/'], [$user->name, $path]])
            ->noindex();
    }

    // ---- čitanje ----

    public function getTitle(): string
    {
        return $this->title ?? config('seo.default_title');
    }

    public function getH1(): ?string
    {
        return $this->h1;
    }

    /**
     * Konačni podaci za <head>, uz pravila iz zahteva (filteri, paginacija, strane naloga).
     *
     * @return array{title: string, description: string, robots: string, canonical: ?string, og: array<string, string>, twitter: array<string, string>, jsonld: list<array<string, mixed>>}
     */
    public function resolve(?Request $request = null): array
    {
        $request ??= request();
        $path = Url::normalizePath($request->getPathInfo());
        $query = Url::withoutTracking($request->query->all());
        $pageKeys = config('seo.page_params', []);
        $filters = array_diff_key($query, array_flip($pageKeys));
        $isPaged = array_intersect_key($query, array_flip($pageKeys)) !== [] || preg_match('#/page/\d+/$#', $path);
        $isAccount = $request->is(...config('seo.noindex_paths', []));

        $indexable = $this->indexable ?? true;
        $canonicalPath = $this->canonical ?? $path;
        $canonicalQuery = [];

        if ($isAccount || ! $indexable || $this->withoutCanonical) {
            $indexable = false;
            $canonical = null;
        } elseif ($filters !== []) {
            // Filtrirana pretraga: noindex, canonical na čistu stranu.
            $indexable = false;
            $canonical = Url::canonical(preg_replace('#page/\d+/$#', '', Url::normalizePath($canonicalPath)));
        } elseif ($isPaged) {
            // Paginacija: noindex, canonical na samu sebe.
            $indexable = false;
            $canonicalQuery = array_intersect_key($query, array_flip($pageKeys));
            $canonical = Url::canonical($path, $canonicalQuery);
        } else {
            $canonical = Url::canonical($canonicalPath);
        }

        $title = $this->getTitle();
        $description = $this->description ?? config('seo.tagline');
        $images = $this->images;

        if ($images === [] && ($default = static::defaultImage())) {
            $images = [$default];
        }

        $og = [
            'og:locale' => 'sr_RS',
            'og:site_name' => config('seo.site_name'),
            'og:type' => $this->ogType,
            'og:title' => $title,
            'og:description' => $description,
            'og:url' => $canonical ?? Url::canonical($path),
        ];

        if ($images !== []) {
            $og['og:image'] = $images[0];
        }

        $og += $this->extraMeta;

        $twitter = array_filter([
            'twitter:card' => $images !== [] ? 'summary_large_image' : 'summary',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $images[0] ?? null,
        ]);

        return [
            'title' => $title,
            'description' => $description,
            'robots' => $indexable ? 'index, follow, max-image-preview:large' : 'noindex, follow',
            'canonical' => $canonical,
            'og' => $og,
            'twitter' => $twitter,
            'jsonld' => $this->jsonLd(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function jsonLd(): array
    {
        $base = config('seo.base_url');
        $graph = [
            array_filter([
                '@type' => 'Organization',
                '@id' => $base.'/#organizacija',
                'name' => config('seo.site_name'),
                'url' => $base.'/',
                'logo' => static::assetExists(config('seo.logo')) ? static::absolute(config('seo.logo')) : null,
                'description' => config('seo.tagline'),
                'email' => config('seo.organization.email'),
                'areaServed' => ['@type' => 'City', 'name' => 'Loznica'],
                'sameAs' => config('seo.organization.same_as'),
            ]),
            [
                '@type' => 'WebSite',
                '@id' => $base.'/#sajt',
                'name' => config('seo.site_name'),
                'url' => $base.'/',
                'inLanguage' => 'sr-Latn-RS',
                'publisher' => ['@id' => $base.'/#organizacija'],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $base.config('seo.search_url')],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];

        if (count($this->breadcrumbs) > 1) {
            $items = [];

            foreach ($this->breadcrumbs as $i => $crumb) {
                $items[] = array_filter([
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $crumb['name'],
                    'item' => $crumb['url'] !== null ? Url::canonical($crumb['url']) : null,
                ]);
            }

            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
        }

        foreach ($this->schemas as $schema) {
            $graph[] = $schema;
        }

        return [['@context' => 'https://schema.org', '@graph' => $graph]];
    }

    // ---- pomoćne ----

    /** Aktivan oglas: objavljen i nije istekao. */
    public static function isActive(Listing $listing): bool
    {
        return $listing->status === ListingStatus::Published
            && ! $listing->trashed()
            && ($listing->expires_at === null || $listing->expires_at->isFuture());
    }

    /** Upit za aktivne oglase (objavljeni, neistekli). */
    public static function activeListings()
    {
        return Listing::query()->published()
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public static function placeMinListings(): int
    {
        return max(1, (int) Settings::get('seo_place_min_listings', config('seo.place_min_listings', 3)));
    }

    /** „1 oglas”, „21 oglas”, „3 oglasa”, „12 oglasa”. */
    public static function countLabel(int $count): string
    {
        $one = $count % 10 === 1 && $count % 100 !== 11;

        return number_format($count, 0, ',', '.').' '.($one ? 'oglas' : 'oglasa');
    }

    /** Title do 60 znakova sa „ | loglasi.rs”; ako je predugačak, skraćuje se sadržaj, ne sufiks. */
    public static function fitTitle(string $text, ?string $shorter = null): string
    {
        $suffix = config('seo.title_suffix');
        $max = (int) config('seo.title_max', 60);
        $text = static::clean($text);

        if (mb_strlen($text.$suffix) <= $max) {
            return $text.$suffix;
        }

        if ($shorter !== null && mb_strlen($shorter.$suffix) <= $max) {
            return $shorter.$suffix;
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    /** Čist tekst bez HTML-a, skraćen na granici reči. */
    public static function excerpt(string $text, int $max): string
    {
        $text = static::clean($text);

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:-–').'…';
    }

    private static function clean(string $text): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ' ', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @return array{0: string, 1: ?string} [naziv za title/H1, akuzativ za opis] */
    private static function phrase(Category $category): array
    {
        $map = config('seo.phrases.'.$category->slug);

        return [$map['naziv'] ?? $category->name, $map['za'] ?? null];
    }

    private static function about(?string $accusative, Category $category): string
    {
        return $accusative !== null ? 'za '.$accusative : 'u kategoriji '.$category->name;
    }

    /** Broj aktivnih oglasa u kategoriji i njenim potkategorijama (opciono u jednom mestu). */
    private static function categoryCount(Category $category, ?Location $location): int
    {
        $ids = Category::query()->where('parent_id', $category->id)->pluck('id')->push($category->id);

        return static::activeListings()->whereIn('category_id', $ids)
            ->when($location, fn ($q) => $q->where('location_id', $location->id))
            ->count();
    }

    private static function number(float $value): string
    {
        return floor($value) === $value ? (string) (int) $value : number_format($value, 2, '.', '');
    }

    private static function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return preg_match('#^https?://#i', $path) ? $path : static::absolute(Storage::disk('public')->url($path));
    }

    private static function absolute(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            // Slike sa diska imaju domen iz APP_URL; u <head> uvek kanonski domen.
            $parts = parse_url($url);

            return config('seo.base_url').($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return config('seo.base_url').'/'.ltrim($url, '/');
    }

    private static function assetExists(?string $path): bool
    {
        return $path !== null && $path !== '' && (preg_match('#^https?://#i', $path) || is_file(public_path(ltrim($path, '/'))));
    }

    private static function defaultImage(): ?string
    {
        $image = config('seo.default_image');

        return static::assetExists($image) ? static::absolute($image) : null;
    }

    /**
     * Radno vreme firme u schema obliku. Prihvata {"mon": "08:00-16:00", ...} ili
     * [{"day": "mon", "open": "08:00", "close": "16:00"}].
     */
    private static function openingHours(mixed $hours): array
    {
        if (! is_array($hours)) {
            return [];
        }

        $days = [
            'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
            'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday',
        ];
        $out = [];

        foreach ($hours as $key => $value) {
            $day = is_array($value) ? ($value['day'] ?? null) : $key;
            $day = $days[Str::lower(Str::substr((string) $day, 0, 3))] ?? null;

            if (is_array($value)) {
                [$open, $close] = [$value['open'] ?? null, $value['close'] ?? null];
            } elseif (is_string($value) && preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $value, $m)) {
                [$open, $close] = [$m[1], $m[2]];
            } else {
                continue;
            }

            if ($day && $open && $close) {
                $out[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $day, 'opens' => $open, 'closes' => $close];
            }
        }

        return $out;
    }
}
