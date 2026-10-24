# Search module

Home page, listing lists (`/oglasi/...`), search and filters, the listing card, and `GET /api/v1/listings`.

## Files

| Path | What |
| --- | --- |
| `app/Services/ListingSearch.php` | The one query builder for web and API: `validate()`, `query()`, `paginate()`, `take()`, `ids()`, `count()`, `listings()` |
| `app/Services/SearchIndex.php` | Keeps `listing_search` in sync: `sync()`, `syncIds()`, `remove()`, `syncUser()`, `syncCategories()`, `refresh()`, `rebuild()` |
| `app/Models/SearchEntry.php` | Model of `listing_search` |
| `app/Support/Search/SearchText.php` | Normalization without diacritics, fulltext terms |
| `app/Support/Search/WebFilters.php` | Web query names, clean URLs, chips, links |
| `app/Support/Search/{CategoryIcons,ImageUrls,TimeAgo,Favorites}.php` | Card helpers |
| `app/Support/Home/{HomeSettings,HomeSections}.php` | Home settings with defaults, section data (cached) |
| `app/Http/Controllers/Search/*` | `HomeController`, `ListingListController`, `ListingApiController` |
| `app/Providers/SearchServiceProvider.php` | Model events, rate limit `search` (120/min per IP), schedule |
| `app/Console/Commands/{SearchRebuild,SearchRefresh,DevListings}.php` | `search:rebuild`, `search:refresh`, `dev:listings` |
| `routes/web/search.php`, `routes/api/search.php` | Routes |
| `resources/views/home/index.blade.php` | Home page |
| `resources/views/search/list.blade.php`, `search/partials/*` | List page, field filter, pages |
| `resources/views/components/listing-card.blade.php` | `<x-listing-card>` |
| `public/css/{home,search,listing-card}.css`, `public/js/{home,search}.js` | Styles and the two small scripts |
| `database/migrations/2026_10_26_000100_search_create_listing_search_table.php` | Index table and indexes |

## API: `GET /api/v1/listings`

Parameters as in the WordPress API and the openapi contract:

| Param | Meaning |
| --- | --- |
| `q` | Text, max 100 characters. Every word is required, words of 3+ letters match as a prefix, diacritics do not matter (`cevapi` = `ćevapi`, `djak` = `đak`). |
| `category` | Category id, includes all subcategories. |
| `location` | Place id. |
| `price_min`, `price_max`, `currency` | Whole numbers in `currency` (EUR default, or RSD); compared in euros (`price_eur`, rate from settings). Listings without a price ("Dogovor") are left out of a price filter. |
| `seller_type` | `private` or `firm` (`business` is read as `firm`). |
| `firm` | **`firms.id`** (not the user id). |
| `f[key]` | Field filters through `ListingFields::parseFilters()` / `apply()`; `f[key]=a,b` for multi, `f[key_od]`, `f[key_do]` for ranges. |
| `sort` | `newest` (default, by `bumped_at`), `price_asc`, `price_desc`, `relevance`. |
| `page`, `per_page` | `per_page` 1 to 50, default 20. |

Always: active listings only (published, not deleted, not expired), promoted first (rank: TOP and Premium XL 3, priority 2, highlighted 1), "Dogovor" last when sorting by price.

Response `{items, page, total}`, items from `ListingFormatter::card()` (listing module), headers `X-WP-Total`, `X-WP-TotalPages`, `Cache-Control: max-age=60`, `ETag` (304 on `If-None-Match`). Errors: 400 `invalid_param` with the WordPress messages in Serbian, all fields at once.

Pages past offset 10,000 (`ListingSearch::MAX_OFFSET`) return no items; `total` stays correct.

## Web pages

- `/` home, `/oglasi/`, `/oglasi/{kategorija}/`, `/oglasi/{kategorija}/{mesto}/`, each with `/page/N/`.
- A second segment that is a child category redirects 301 to `/oglasi/{child}/`; anything else that is not a place is 404.
- Query names: `keyword` (also `s`, `q`), `kategorija`, `mesto`, `cena_od`, `cena_do`, `valuta`, `prodavac` (`privatno`, `firma`), `sortiranje` (`najnovije`, `cena-rastuce`, `cena-opadajuce`, `relevantnost`), `f[key]` (checkbox arrays are joined).
- `kategorija` and `mesto` from the query move to the path, empty fields and the default sort are dropped (302). `/page/1/` is 301 to the page itself; a page past the last one is 404. `?page=N` and `?paged=N` are read too.
- `/?s=...` (old WordPress search) shows the list without a redirect.
- 24 listings per page. Filters are a GET form that works without JavaScript: a sticky sidebar on desktop, a full screen panel on phones opened by the `#filteri` link (`:target`). `search.js` only submits on sort change and skips empty fields.
- Invalid filter values are dropped with a notice instead of an error page.
- Logged in users get the heart on every card (POST `/moj-nalog/omiljeni/{id}/`) and a "Sačuvaj pretragu" form that POSTs the current query (same names as above, with `kategorija` and `mesto` slugs) to `/moj-nalog/pretrage/`.

### SEO

`Seo::for($category, ['location' => ..., 'count' => ...])` or `Seo::get()->listings($count)`; the count is always that of the clean page (cached). The SEO module then applies: noindex for filters with canonical to the clean page, noindex with self canonical for page 2+, place pages indexable from 3 listings (admin setting), empty category noindex.

## Search index (`listing_search`)

One row per active listing (`id` = `listings.id`), with the category path (`cat1`..`cat3`), place, `is_firm`, `price_eur`, `no_price`, `featured`, `sort_at` (`bumped_at`), `expires_at` and `search_text` (FULLTEXT).

- Every list path reads ids from one composite index that also gives the order (`ls_all`, `ls_cat1/2/3`, `ls_cat{1,2}_location`, `ls_location`, `ls_seller`, `ls_price_asc/desc` and `ls_cat{1,2}_price_asc/desc` with DESC parts, `ls_newest`). Only the page's ids are then loaded as models with the card relations (5 queries per page, no N+1).
- Field filters are `EXISTS` subqueries on `listing_field_values`, covered by `lfv_search (listing_id, field_id, value, value_num)`, `lfv_value (field_id, value, listing_id)` and `lfv_num (field_id, value_num, listing_id)`.
- Fulltext uses InnoDB tokens of 3+ characters. Shorter words and InnoDB stopwords are stored again with the suffix `qqq` (`a4` -> `a4qqq`) so they are found through the index too.
- Counts are cached for 60 s under a version key that every index write changes, so a new listing shows at once.

### Keeping it in sync

- `Listing` saved, deleted, restored -> `SearchIndex::sync()`. User `account_type` change -> `syncUser()`. Category `parent_id` change -> `syncCategories()`.
- `search:refresh` runs every minute (needs the `schedule:run` cron): removes expired listings and switches featured flags. Expiry and the end of a featured period can be up to one minute late.
- Writes that skip Eloquent (the importer uses `DB::table`, mass updates) must call `SearchIndex::syncIds($ids)` or be followed by `php artisan search:rebuild`. The rebuild empties the table first, so lists are empty for its duration (about 10 s at 200k).

## Benchmark (200,000 listings, 183,866 active)

`dev:listings 200000`, MariaDB 10.11 with a 128 MB buffer pool, warm cache. "ids" is the search query for one page, "count" is the uncached total (cached afterwards), "page" is the full `paginate()` with models and relations.

| Case | ids ms | count ms | page ms |
| --- | ---: | ---: | ---: |
| all, newest | 0.5 | 21.9 | 6.2 |
| all, page 100 | 0.9 | 22.9 | 6.3 |
| category | 0.4 | 4.4 | 5.8 |
| subcategory | 0.4 | 1.0 | 5.5 |
| category + place | 0.4 | 2.6 | 5.2 |
| place | 0.4 | 24.8 | 5.5 |
| q "golf" | 10.6 | 9.8 | 17.1 |
| q "golf", relevance | 11.7 | 10.8 | 18.9 |
| q "a4" (short word) | 9.3 | 9.1 | 17.5 |
| q "ćevapi" + category | 21.2 | 20.7 | 26.7 |
| price asc / desc | 0.5 | 21.4 | 6.1 |
| category, price asc | 0.4 | 4.4 | 5.6 |
| price range | 0.9 | 41.6 | 6.1 |
| price range, price sort | 1.0 | 41.7 | 6.6 |
| firm / private | 0.5 / 0.7 | 5.6 / 24.1 | 6.1 |
| f marka + godište | 12.9 | 12.4 | 19.4 |
| f stanje | 18.9 | 27.8 | 27.7 |

Every search query is under 30 ms. A few uncached counts on broad filters take 25 to 42 ms once per minute.

TTFB on `php artisan serve` (median of 5, warm): home 51 ms, `/oglasi/` 54 ms, category 55 ms, category + place 62 ms, keyword + price 78 ms, keyword 78 ms, page 50: 55 ms, API with `q` 45 ms. The first request after a cache clear builds the home sections and counts (about 0.5 s).

## Home page

Eight sections ported from loglasi-core `inc/home`: intro (H1 from the SEO module, chips), categories, featured (hidden when empty), latest (plain newest order, from the 7th card hidden on phones), category tabs (desktop only; all panels show without JavaScript), firms (offer from `Cenovnik::offerText()`, firm strip from `home.firms_min` firms), why loglasi.rs, about the site (Markdown).

The Premium section ("Premium XL + početna", `premium_home_until` running) sits after the categories: up to `home.premium_count` (4) wide cards, a random pick among all running ones for each cache period, two per row on desktop and one column on phones; those listings are not repeated among the featured ones. Hidden when empty.

Section data is cached for 60 s (`home.sections`, ids and plain values only) and cleared on any change to listings, images, categories, firms or account types.

Settings keys (`Settings::get`, defaults in `HomeSettings::DEFAULTS`): `home.intro_text`, `home.intro_label`, `home.intro_terms` (comma list; empty = biggest categories), `home.intro_terms_count`, `home.categories_title`, `home.categories_count`, `home.premium_title`, `home.premium_count`, `home.featured_title`, `home.featured_count`, `home.featured_link_text`, `home.featured_link_url`, `home.latest_title`, `home.latest_count`, `home.latest_mobile_count`, `home.tabs_title`, `home.tabs_categories` (slugs), `home.tabs_count`, `home.firms_title`, `home.firms_text`, `home.firms_button`, `home.firms_url`, `home.firms_min`, `home.firms_max`, `home.firms_show_remaining`, `home.why_title`, `home.about_title`, `home.about`.

## Listing card

`<x-listing-card :listing="$listing" />` with optional `highlight` (orange frame), `eager` (first images), `layout` (`grid` or `list`, a row on phones), `heading` (`h2` to `h4`), `xl` (Premium XL card). Load `ListingSearch::cardRelations()` first; with `fieldValues` loaded the card also shows up to three field values. Extra attributes (for example `class`) go to the root `<article>`.

### Premium XL card

A listing with a running `premium_xl_until` ranks with TOP (rank 3 in `listing_search.featured`, `SearchIndex::RANKS`), so it is at the top of its category and search, and `featured_until` is the earliest end of any running promotion. On the list pages `PremiumXl::cards($results)` picks the wide cards: at most one per `config('payments.premium_xl_every')` (10) listings of the page, rounded up (24 per page: 3), the first XL listings in list order; the others keep their rank on a normal card.

The card (`lc--xl`) has the "Premium" badge, a bigger image (800/1600 px sources), title and price, a description excerpt (180 characters) and a "Kontaktirajte prodavca" button (the whole card links to the listing). It spans two columns of a grid declared as `container: lc-grid / inline-size` once that grid is at least 520 px wide (`.sr-grid`, which also packs with `grid-auto-flow: dense`), and puts the text beside the image once the card is at least 480 px wide. Phones: one column, full width image on top. Without container query support it is a normal size card with the badge.

No new column or index: the rank reuses every existing index. After deploying, run `php artisan search:rebuild` once so running Premium XL periods get their rank.

## Development data

`php artisan dev:listings {count=2000} {--fresh}` creates sellers (`@primer.loglasi.test`), listings with Serbian titles, EUR/RSD prices, "Dogovor", featured listings and field values, then rebuilds the index. It refuses to run in production and needs the catalog seeded.

## Open questions

- Should the `loglasi_pocetna_*` texts from WordPress be imported into the `home.*` settings?
- Pagination keeps the WordPress `/page/N/` style; keep it, or move to `?page=N`?
- Is the 10,000 offset cap fine for the app (deep pages return empty items)?
