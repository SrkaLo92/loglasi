<?php

namespace App\Support;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use App\Services\Catalog;
use App\Services\ListingFields;
use App\Support\Pages\Company;
use App\Support\Pages\PageText;
use App\Support\Seo\Url;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SEO data of one page: title, description, canonical, robots, Open Graph, JSON-LD.
 * One instance per request; the controller fills it, `partials/seo-head` prints it.
 *
 *   Seo::for($listing);                      // listing, category, firm, profile
 *   Seo::for($category, ['location' => $placeModel, 'count' => 12]);
 *   Seo::get()->page('Česta pitanja', 'Odgovori na ...')->faq($questions);
 *   Seo::get()->title('...')->description('...')->noindex();
 *
 * The noindex and canonical rules for the query (filters, pagination) and for the account pages
 * are applied on their own, in resolve(). Description: docs/moduli/seo.md
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

    /** schema.org type of the page node: WebPage, CollectionPage (lists), ProfilePage (profiles). */
    private string $pageType = 'WebPage';

    /** @var list<string> */
    private array $images = [];

    /** @var array{0: int, 1: int}|null width and height of the first image, when known */
    private ?array $imageSize = null;

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

    /** Fills the page from a model: Listing, Category (+ place), User (firm or private profile), Firm. */
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

    /** Generated text is cut at 155 characters; a text entered in the admin goes as it is. */
    public function description(?string $description, bool $literal = false): self
    {
        $this->description = match (true) {
            $description === null => null,
            $literal => static::clean($description),
            default => static::excerpt($description, (int) config('seo.description_max', 155)),
        };

        return $this;
    }

    public function h1(?string $h1): self
    {
        $this->h1 = $h1;

        return $this;
    }

    /** Path (/oglasi/x/) or absolute URL; the domain is always the canonical one. */
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

    public function pageType(string $type): self
    {
        $this->pageType = $type;

        return $this;
    }

    /** Open Graph type: website, product, profile, article. */
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

    /** @param list<array{0: string, 1?: ?string}|array{name: string, url?: ?string}> $items [name, path] */
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

    /** Extra JSON-LD object (without @context). */
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

    // ---- ready-made templates (keyword map) ----

    public function home(): self
    {
        return $this->title(static::withSuffix(static::text('home.title')))
            ->h1('Oglasi za Loznicu i okolinu')
            ->description(static::text('home.description'), true)
            ->canonical('/');
    }

    /** Plain page (O nama, FAQ, Kontakt...). */
    public function page(string $name, ?string $description = null, ?string $path = null): self
    {
        $this->title(static::fitTitle($name))->h1($name)->description($description ?? config('seo.tagline'));

        if ($path !== null) {
            $this->canonical($path);
        }

        return $this->breadcrumbs([['Početna', '/'], [$name, $path]]);
    }

    /** /oglasi/ (all listings). */
    public function listings(?int $count = null): self
    {
        $count ??= static::activeListings()->count();

        $this->title(static::withSuffix(static::text('archive.title')))
            ->h1(static::text('archive.h1'))
            ->description(static::text('archive.description'), true)
            ->canonical('/oglasi/')
            ->pageType('CollectionPage')
            ->breadcrumbs([['Početna', '/'], ['Oglasi', '/oglasi/']]);

        return $count === 0 ? $this->noindex() : $this;
    }

    public function listing(Listing $listing): self
    {
        $listing->loadMissing(['category.parent', 'location', 'user.firm', 'images']);
        $place = $listing->location?->name ?: 'Loznica';
        $top = $listing->category ? static::topCategory($listing->category) : null;
        $topSlug = $top?->slug;
        $vars = [
            '{naslov}' => static::clean((string) $listing->title).static::titleSuffix($listing),
            '{kategorija}' => $top ? static::categoryName($top) : '',
            '{mesto}' => $place,
        ];

        $title = static::withSuffix(static::text('listing.title', $vars));
        if (mb_strlen($title) > 70) {
            $title = static::withSuffix(static::text('listing.title_short', $vars));
        }

        $this->h1($listing->title)
            ->title($title)
            ->canonical('/oglas/'.$listing->slug.'/');

        // Price is left out for jobs (it is a salary) and when there is none.
        $hasPrice = $listing->price !== null && (float) $listing->price > 0 && $topSlug !== 'poslovi';
        $this->type($hasPrice ? 'product' : 'article');
        $price = $hasPrice ? 'Cena '.$listing->priceLabel().'. ' : '';
        $text = static::clean((string) $listing->description);

        $this->description(mb_strlen($text) >= 60
            ? $price.$text
            : static::text('listing.description', $vars + ['{cena}' => $price]));

        if ($listing->published_at ?? $listing->created_at) {
            $this->meta('article:published_time', ($listing->published_at ?? $listing->created_at)->toAtomString());
        }
        if ($listing->updated_at) {
            $this->meta('article:modified_time', $listing->updated_at->toAtomString());
        }

        // JPEG 800 px first: share previews (WhatsApp, Viber) skip large WebP originals.
        $images = $listing->images->take(6)->map(fn ($image) => static::imageUrl($image->variants['md']['jpg'] ?? $image->variants['lg']['jpg'] ?? $image->path))->filter()->values()->all();
        $this->image($images);
        $cover = $listing->images->first();
        if ($cover && $cover->width > 0 && $cover->height > 0) {
            $variant = isset($cover->variants['md']['jpg']) ? $cover->variants['md'] : ($cover->variants['lg'] ?? []);
            $width = (int) ($variant['width'] ?? $cover->width);
            $this->imageSize = [$width, (int) round($cover->height * $width / $cover->width)];
        }

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

        if (! $hasPrice || in_array($topSlug, ['usluge', 'poslovi'], true)) {
            return $this;
        }

        $product = [
            '@type' => 'Product',
            '@id' => Url::canonical('/oglas/'.$listing->slug.'/').'#product',
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

        if ($topSlug === 'automobili') {
            $product = static::car($product, $listing);
        }

        if ($hasPrice) {
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

            $offer['areaServed'] = ['@type' => 'City', 'name' => $place];

            if ($condition = app(ListingFields::class)->condition($listing)) {
                $offer['itemCondition'] = $condition === 'novo' ? 'https://schema.org/NewCondition' : 'https://schema.org/UsedCondition';
            }

            $product['offers'] = $offer;
            $this->meta('product:price:amount', static::number((float) $listing->price))
                ->meta('product:price:currency', $listing->currency->value);
        }

        return $this->schema($product);
    }

    /** /oglasi/{category}/ and /oglasi/{category}/{place}/ */
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
            $vars = ['{kategorija}' => static::categoryName($category), '{o_cemu}' => static::about($accusative, $category)];

            $this->title($category->seo_title ?: static::withSuffix(static::text('category.title', $vars)))
                ->h1($category->seo_h1 ?: static::text('category.h1', $vars))
                ->description($category->seo_description ?: (static::clean((string) $category->description) ?: static::text('category.description', $vars)), (bool) $category->seo_description);

            $indexable = $count > 0;
        }

        $this->canonical($path)->breadcrumbs($crumbs)->type('website')->pageType('CollectionPage');

        if (! $location) {
            $this->faq(static::faqItems($category->seo_faq));
        }

        return $indexable ? $this : $this->noindex();
    }

    /**
     * FAQ of a category from its admin field: question on the first line, answer on the
     * next ones, blocks separated by an empty line.
     *
     * @return list<array{q: string, a: string}>
     */
    public static function faqItems(?string $text): array
    {
        $items = [];

        foreach (preg_split('/\R\s*\R/u', trim((string) $text)) ?: [] as $block) {
            $lines = preg_split('/\R/u', trim($block)) ?: [];
            $question = trim((string) array_shift($lines));
            $answer = trim(implode(' ', array_map('trim', $lines)));

            if ($question !== '' && $answer !== '') {
                $items[] = ['q' => $question, 'a' => $answer];
            }
        }

        return $items;
    }

    /** ItemList of the newest listings on a list page. @param iterable<Listing> $listings */
    public function itemList(iterable $listings, string $path): self
    {
        $items = [];
        foreach ($listings as $listing) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'url' => Url::canonical('/oglas/'.$listing->slug.'/'),
                'name' => static::clean((string) $listing->title),
            ];
            if (count($items) === 12) {
                break;
            }
        }

        return $items === [] ? $this : $this->schema(['@type' => 'ItemList', '@id' => Url::canonical($path).'#listings', 'itemListElement' => $items]);
    }

    /** Firm profile (/korisnik/{slug}/): indexable with an active business package, LocalBusiness schema. */
    public function firm(User $user, ?string $activity = null): self
    {
        $user->loadMissing('firm.location');
        $firm = $user->firm;
        $name = $firm?->name ?: $user->name;
        $path = '/korisnik/'.$user->slug.'/';

        $vars = ['{firma}' => static::clean($name), '{mesto}' => $firm?->location?->name ?: 'Loznica'];

        $this->h1($name)
            ->title(static::withSuffix(static::text('business.title', $vars)))
            ->description(static::clean((string) $firm?->description) ?: static::text('business.description', $vars))
            ->canonical($path)
            ->type('profile')
            ->pageType('ProfilePage')
            ->breadcrumbs([['Početna', '/'], [$name, $path]]);

        if ($firm?->logo_path) {
            $this->image(static::imageUrl($firm->logo_path));
        }

        if (! static::firmIndexable($user)) {
            return $this->noindex();
        }

        $business = [
            '@type' => 'LocalBusiness',
            '@id' => Url::canonical($path).'#business',
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

        // E.164 only; a number that cannot be normalized is left out.
        $phone = Phone::normalize($firm->phone ?: $user->phone);
        if ($phone !== '') {
            $business['telephone'] = $phone;
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

    /** Private person's profile: 200, noindex, follow. */
    public function profile(User $user): self
    {
        $path = '/korisnik/'.$user->slug.'/';

        return $this->title(static::withSuffix(static::text('person.title', ['{ime}' => static::clean((string) $user->name)])))
            ->h1($user->name)
            ->description('Oglasi korisnika '.$user->name.' na loglasi.rs, sajtu za oglase za Loznicu i okolinu.')
            ->canonical($path)
            ->type('profile')
            ->pageType('ProfilePage')
            ->breadcrumbs([['Početna', '/'], [$user->name, $path]])
            ->noindex();
    }

    // ---- reading ----

    public function getTitle(): string
    {
        return $this->title ?? config('seo.default_title');
    }

    public function getH1(): ?string
    {
        return $this->h1;
    }

    /**
     * Final data for <head>, with the rules from the request (filters, pagination, account pages).
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
            // Filtered search: noindex, canonical to the clean page.
            $indexable = false;
            $canonical = Url::canonical(preg_replace('#page/\d+/$#', '', Url::normalizePath($canonicalPath)));
        } elseif ($isPaged) {
            // Pagination: indexable with a self canonical, so Google reaches older listings.
            $canonicalQuery = array_intersect_key($query, array_flip($pageKeys));
            $canonical = Url::canonical($path, $canonicalQuery);
        } else {
            $canonical = Url::canonical($canonicalPath);
        }

        $title = $this->getTitle();
        $description = $this->description ?? config('seo.tagline');
        $pageNumber = static::pageNumber($path, $query);

        if ($pageNumber > 1) {
            $title = static::paged($title, $pageNumber);
        }
        $images = $this->images;
        $usesDefault = false;

        if ($images === [] && ($default = static::defaultImage())) {
            $images = [$default];
            $usesDefault = true;
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
            if ($usesDefault) {
                $og['og:image:width'] = '1200';
                $og['og:image:height'] = '630';
            } elseif ($this->imageSize !== null) {
                [$og['og:image:width'], $og['og:image:height']] = array_map('strval', $this->imageSize);
            }
            $og['og:image:alt'] = $usesDefault ? config('seo.site_name').': '.config('seo.default_image_alt', 'Oglasi za Loznicu i okolinu') : ($this->getH1() ?? $title);
        }

        $og += $this->extraMeta;

        $twitter = array_filter([
            'twitter:card' => $images !== [] ? 'summary_large_image' : 'summary',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $images[0] ?? null,
        ]);

        // Staging and local copies stay out of search engines whatever the page says.
        if (! config('seo.indexable')) {
            $indexable = false;
        }

        return [
            'title' => $title,
            'description' => $description,
            'robots' => $indexable ? 'index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1' : 'noindex, follow',
            'canonical' => $canonical,
            'og' => $og,
            'twitter' => $twitter,
            'jsonld' => $this->jsonLd($title, $description, $canonical ?? Url::canonical($path), $images[0] ?? null),
        ];
    }

    /**
     * One @graph as on the old site: Organization, WebSite, the page node, BreadcrumbList and the
     * page's own nodes (Product, LocalBusiness, FAQPage, ItemList).
     *
     * @return list<array<string, mixed>>
     */
    public function jsonLd(?string $name = null, ?string $description = null, ?string $url = null, ?string $image = null): array
    {
        $base = config('seo.base_url');
        $logo = static::assetExists(config('seo.logo')) ? static::absolute(config('seo.logo')) : null;
        $phone = Phone::normalize((string) Settings::get('contact_phone'));
        $email = (string) Settings::get('contact_email', config('seo.organization.email'));
        $organization = array_filter([
            '@type' => 'Organization',
            '@id' => $base.'/#organization',
            'name' => config('seo.site_name'),
            'legalName' => Company::name() ?: null,
            'taxID' => Company::pib() ?: null,
            'url' => $base.'/',
            'logo' => $logo ? ['@type' => 'ImageObject', '@id' => $base.'/#logo', 'url' => $logo, 'contentUrl' => $logo] : null,
            'image' => $logo ? ['@id' => $base.'/#logo'] : null,
            'description' => config('seo.tagline'),
            'email' => $email !== '' ? $email : null,
            'telephone' => $phone !== '' ? $phone : null,
            'address' => [
                '@type' => 'PostalAddress',
                'postalCode' => config('seo.organization.postal_code'),
                'addressLocality' => config('seo.organization.city'),
                'addressCountry' => 'RS',
            ],
            'areaServed' => array_map(fn ($city) => ['@type' => 'City', 'name' => $city], config('seo.organization.area_served', [])),
            'sameAs' => config('seo.organization.same_as'),
        ]);

        $graph = [
            $organization,
            [
                '@type' => 'WebSite',
                '@id' => $base.'/#website',
                'name' => config('seo.site_name'),
                'url' => $base.'/',
                'inLanguage' => 'sr-RS',
                'publisher' => ['@id' => $base.'/#organization'],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $base.config('seo.search_url')],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];

        if ($url !== null) {
            $isHome = rtrim($url, '/') === $base;
            $graph[] = array_filter([
                '@type' => $this->pageType,
                '@id' => $url.'#webpage',
                'url' => $url,
                'name' => $name,
                'description' => $description,
                'inLanguage' => 'sr-RS',
                'isPartOf' => ['@id' => $base.'/#website'],
                'primaryImageOfPage' => $image ? ['@type' => 'ImageObject', 'url' => $image] : null,
                'about' => $isHome ? ['@id' => $base.'/#organization'] : null,
                'breadcrumb' => count($this->breadcrumbs) > 1 ? ['@id' => $url.'#breadcrumb'] : null,
            ]);
        }

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

            $graph[] = array_filter(['@type' => 'BreadcrumbList', '@id' => $url !== null ? $url.'#breadcrumb' : null, 'itemListElement' => $items]);
        }

        foreach ($this->schemas as $schema) {
            $graph[] = $schema;
        }

        return [['@context' => 'https://schema.org', '@graph' => $graph]];
    }

    // ---- helpers ----

    /** SEO text from PageText (admin can change it), with {x} replaced and empty commas or brackets removed. */
    public static function text(string $key, array $vars = []): string
    {
        $text = strtr(PageText::get('seo.'.$key), $vars);
        $text = (string) preg_replace(['/\(\s*\)/u', '/\s+,/u', '/,\s*(?=,|\||$)/u', '/\s{2,}/u'], ['', ',', '', ' '], $text);

        return trim($text, " \t\n,");
    }

    /** Adds „ | loglasi.rs” unless the title already names the site. */
    public static function withSuffix(string $title): string
    {
        $title = static::clean($title);

        return str_contains($title, (string) config('seo.site_name')) ? $title : $title.config('seo.title_suffix');
    }

    /** "Svi oglasi Loznica, strana 2 | loglasi.rs" */
    public static function paged(string $title, int $page): string
    {
        $suffix = (string) config('seo.title_suffix');

        return str_ends_with($title, $suffix)
            ? mb_substr($title, 0, mb_strlen($title) - mb_strlen($suffix)).', strana '.$page.$suffix
            : $title.', strana '.$page;
    }

    /** Category name without the old „Parent | ” prefix (WordPress term names). */
    public static function categoryName(Category $category): string
    {
        $name = static::clean(html_entity_decode((string) $category->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if (! str_contains($name, '|')) {
            return static::withParentWhenShared($category, $name);
        }

        $parts = array_map('trim', explode('|', $name));
        $parent = $category->parent_id ? static::clean((string) $category->parent?->name) : '';

        if ($parent !== '' && mb_strtolower($parts[0]) === mb_strtolower($parent) && count($parts) > 1) {
            array_shift($parts);
        }

        return implode(' – ', array_filter($parts, fn ($part) => $part !== ''));
    }

    /** "Ostalo" exists under many categories: the parent makes the title and H1 unique ("Ostalo – Nameštaj"). */
    private static function withParentWhenShared(Category $category, string $name): string
    {
        if (! $category->parent_id) {
            return $name;
        }
        $plain = mb_strtolower($name);
        $shared = app(Catalog::class)->categories()
            ->filter(fn (Category $c) => $c->id !== $category->id && mb_strtolower(trim((string) $c->name)) === $plain)
            ->isNotEmpty();
        $parent = $shared ? static::clean((string) app(Catalog::class)->category((int) $category->parent_id)?->name) : '';

        return $parent !== '' ? $name.' – '.$parent : $name;
    }

    private static function topCategory(Category $category): Category
    {
        $guard = 0;
        while ($category->parent_id && $guard++ < 10) {
            $parent = $category->parent ?? Category::query()->find($category->parent_id);
            if (! $parent) {
                break;
            }
            $category = $parent;
        }

        return $category;
    }

    private static function pageNumber(string $path, array $query): int
    {
        if (preg_match('#/page/(\d+)/$#', $path, $m)) {
            return (int) $m[1];
        }

        foreach (config('seo.page_params', []) as $key) {
            if (isset($query[$key]) && is_scalar($query[$key]) && ctype_digit((string) $query[$key])) {
                return (int) $query[$key];
            }
        }

        return 1;
    }

    /**
     * A firm profile is indexed (and in the sitemap) once it has an active listing or a filled
     * profile (description or logo), or a paid package; an empty profile stays noindex.
     */
    public static function firmIndexable(User $user): bool
    {
        $firm = $user->firm;

        if (! $user->isFirm() || ! $firm) {
            return false;
        }

        return $firm->hasPackage()
            || trim((string) $firm->description) !== ''
            || ! empty($firm->logo_path)
            || static::activeListings()->where('user_id', $user->id)->exists();
    }

    /** Active listing: published and not expired. */
    /**
     * Sellers repeat titles (seven „Crane šorc” listings): the duplicates get the price, or the
     * listing number when the price is the same too, so every page has its own <title>.
     */
    private static function titleSuffix(Listing $listing): string
    {
        $prices = static::activeListings()
            ->where('user_id', $listing->user_id)
            ->where('title', $listing->title)
            ->whereKeyNot($listing->id)
            ->pluck('price');
        if ($prices->isEmpty()) {
            return '';
        }

        $price = $listing->price !== null && (float) $listing->price > 0 ? $listing->priceLabel() : null;
        $samePrice = $prices->contains(fn ($p) => $p !== null && $listing->price !== null && (float) $p === (float) $listing->price);

        return $price !== null && ! $samePrice ? ', '.$price : ', br. '.$listing->id;
    }

    public static function isActive(Listing $listing): bool
    {
        return $listing->status === ListingStatus::Published
            && ! $listing->trashed()
            && ($listing->expires_at === null || $listing->expires_at->isFuture());
    }

    /** Query for active listings (published, not expired). */
    public static function activeListings()
    {
        return Listing::query()->published()
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public static function placeMinListings(): int
    {
        return max(1, (int) Settings::get('seo_place_min_listings', config('seo.place_min_listings', 3)));
    }

    /** "1 oglas", "21 oglas", "3 oglasa", "12 oglasa". */
    public static function countLabel(int $count): string
    {
        $one = $count % 10 === 1 && $count % 100 !== 11;

        return number_format($count, 0, ',', '.').' '.($one ? 'oglas' : 'oglasa');
    }

    /** Title up to 60 characters with " | loglasi.rs"; if it is too long, the content is shortened, not the suffix. */
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

    /** Plain text without HTML, cut at a word boundary. */
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

    /** @return array{0: string, 1: ?string} [name for title/H1, accusative for the description] */
    private static function phrase(Category $category): array
    {
        $map = config('seo.phrases.'.$category->slug);

        return [$map['naziv'] ?? $category->name, $map['za'] ?? null];
    }

    private static function about(?string $accusative, Category $category): string
    {
        return $accusative !== null ? 'za '.$accusative : 'u kategoriji '.$category->name;
    }

    /** Number of active listings in the category and its subcategories (optionally in one place). */
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
            // Images from the disk have the APP_URL domain; <head> always uses the canonical domain.
            $parts = parse_url($url);

            return config('seo.base_url').($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return config('seo.base_url').'/'.ltrim($url, '/');
    }

    private static function assetExists(?string $path): bool
    {
        return $path !== null && $path !== '' && (preg_match('#^https?://#i', $path) || is_file(public_path(ltrim($path, '/'))));
    }

    /** A car listing is also a schema.org Car: brand, model year, mileage and fuel from the listing fields. */
    private static function car(array $product, Listing $listing): array
    {
        $fields = collect(app(ListingFields::class)->values($listing))->keyBy('key');
        $product['@type'] = ['Product', 'Car'];

        if ($brand = $fields->get('marka_automobila')['display'] ?? null) {
            $product['brand'] = ['@type' => 'Brand', 'name' => $brand];
        }
        if ($model = $fields->get('model_automobila')['display'] ?? null) {
            $product['model'] = $model;
        }
        if (is_numeric($year = $fields->get('godina')['value'] ?? null)) {
            $product['vehicleModelDate'] = (string) (int) $year;
        }
        if (is_numeric($km = $fields->get('kilometraza')['value'] ?? null)) {
            $product['mileageFromOdometer'] = ['@type' => 'QuantitativeValue', 'value' => (int) $km, 'unitCode' => 'KMT'];
        }
        if ($fuel = $fields->get('gorivo')['display'] ?? null) {
            $product['fuelType'] = $fuel;
        }

        return $product;
    }

    private static function defaultImage(): ?string
    {
        $image = config('seo.default_image');

        return static::assetExists($image) ? static::absolute($image) : null;
    }

    /**
     * Firm opening hours in schema form. Accepts {"mon": "08:00-16:00", ...} or
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
