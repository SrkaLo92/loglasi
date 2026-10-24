# Listing: listing page, user and firm profiles, listing API

The module renders `/oglas/{slug}/` (gallery, price, fields, description, seller card, phone reveal, contact buttons, sticky mobile bar, share, report form, loan calculator, similar listings) and `/korisnik/{slug}/` (rich firm profile or simple private profile). It also serves the listing, similar, contact, firm and report endpoints of the mobile API. Markup and CSS classes are ported from the WordPress `loglasi-core` modules `inc/oglas` (prefix `lgo-`) and `inc/profil` (prefix `lgp-`).

## Files

| File | What |
| --- | --- |
| `app/Http/Controllers/Listing/ListingController.php` | listing page, `POST /oglas/{slug}/telefon/`, `POST /oglas/{slug}/prijava/` |
| `app/Http/Controllers/Listing/ProfileController.php` | `/korisnik/{slug}/` (firm or private) |
| `app/Http/Controllers/Listing/Api/*` | `ListingApiController`, `FirmApiController`, `ReportApiController` |
| `app/Http/Api/ListingFormatter.php` | contract shapes: `card()`, `detail()`, `seller()`, `firm()`, `image()`, `status()` |
| `app/Policies/ListingPolicy.php` | `view`, `revealPhone`, `report` |
| `app/Providers/ListingServiceProvider.php` | rate limiters, cache invalidation on listing events |
| `app/Support/Listing/Stats.php` | view, phone reveal and profile view counting, daily stats, firm totals |
| `app/Support/Listing/Seller.php` | seller card data (phone order, logo, active count, contact links) |
| `app/Support/Listing/SimilarListings.php` | similar listings (API and page), cached and indexed |
| `app/Support/Listing/Text.php` | Serbian plurals, dates ("pre 2 sata", "Na sajtu od oktobra 2026."), initials, safe description |
| `app/Support/Listing/WorkingHours.php` | opening hours parsing and "Otvoreno do 17:00" status (same algorithm as `listing-profile.js`) |
| `app/Support/Listing/LoanCalculator.php`, `ReportReasons.php`, `Icons.php` | loan example, report reasons, inline SVG icons |
| `app/Models/ListingDailyStat.php` | model for `listing_daily_stats` |
| `config/listing.php` | profile edit path, safety tips |
| `routes/web/listing.php`, `routes/api/listing.php` | routes |
| `resources/views/listing/` | `show`, `profile-firm`, `profile-private`, partials in `partials/` and `profile/` |
| `public/css/listing.css`, `public/js/listing.js` | listing page (no libraries) |
| `public/css/listing-profile.css`, `public/js/listing-profile.js` | profile pages |
| `database/migrations/2026_10_26_000100_listing_add_profile_and_stats_columns.php` | firm columns and the seller index |
| `tests/Feature/Listing/` | tests |

## Listing page `/oglas/{slug}/`

Status rules (`ListingPolicy::view`):

| Listing | Public | Owner and staff |
| --- | --- | --- |
| published, not expired | 200, indexable | 200 |
| expired (status or `expires_at` in the past) | 200 with notice, `noindex` (from `Seo::for`) | 200 |
| pending, draft, rejected | 404 | 200 with a notice (rejection reason for rejected) |
| soft-deleted | 410 (Gone middleware) | 410 |
| seller blocked | 404 | 200 |

- H1 comes from `Seo::get()->getH1()`; `Seo::for($listing)` sets title, canonical, robots and JSON-LD.
- Gallery: CSS scroll-snap track with `#slika-N` anchors and thumbnails, so it works without JS; arrows appear with JS. First image `loading="eager" fetchpriority="high"`, the rest lazy; every `img` has width and height.
- Image variants: `listing_images.variants` is read as `{"sm": {"webp": path, "jpg": path, "width": 400}, "md": {...800}, "lg": {...1600}}`. `<picture>` gets a WebP `srcset`, the `img` falls back to the md JPEG and then to the original. Without variants the original is used. The posting module writes this JSON.
- Description: escaped, paragraphs on blank lines, `nl2br`; `http(s)://` and `www.` links get `rel="nofollow ugc noopener" target="_blank"`. Nothing else is turned into HTML.
- Phone: masked (`064 123 ••••`) with a "Prikaži broj" form. Without JS the form posts and redirects back with the number visible (kept in the session). With JS, `fetch` returns `{phone, display, tel, whatsapp, viber}` and the buttons turn into links. The big button reveals first; the bar buttons and WhatsApp/Viber reveal and open right away.
- Phone order: `listings.contact_phone`, then `firms.phone`, then `users.phone`. WhatsApp/Viber for firms use `firms.whatsapp` / `firms.viber` (null means automatic for mobile numbers).
- "Poruka" links to `/moj-nalog/poruke/novo/?oglas={id}` (messages module).
- Sticky mobile bar: shown to everyone except the owner when the listing is active; it hides while the card buttons are on screen. The site bottom nav is hidden on this page (`$hideMobileNav`).
- Report form: `<details>` with radio reasons (`prevara`, `pogresna_kategorija`, `duplikat`, `neprikladno`, `prodato`, `drugo`) and up to 1000 characters. It has a honeypot field `website`. A user's duplicate open report creates no new row. The owner gets 404.
- Similar listings: up to 8 cards. The category itself is used first; parent levels are added only while it has fewer than 4. The link reads "Svi oglasi: {category} (N)".
- Loan calculator "Kreditni kalkulator (informativno)" is collapsed below the contact card. It shows only for priced listings of at least 1000 EUR. Server example: 20% down payment, 60 months, 8%. JS recalculates.

## Counting

All counters use query-builder `increment()` plus one `INSERT ... ON DUPLICATE KEY UPDATE col = col + 1` on `listing_daily_stats` (primary key `listing_id, date`, date in Europe/Belgrade). There is no read-modify-write, no model events and no race.

| Counter | When | Dedup |
| --- | --- | --- |
| `listings.views` + daily `views` | web page view, API detail | web: once per session; API: `Cache::add` per user or IP+UA for 6 h |
| `listings.phone_reveals` + daily `phone_reveals` | web reveal, `POST /listings/{id}/contact` | web: once per session; API: once a day per user |
| `firms.profile_views` | firm profile view | once per session |

Bots (user agent pattern), the owner and staff are never counted.

## Profile `/korisnik/{slug}/`

- Firm (`account_type = firm`) has:
  - Header: cover or brand gradient, logo or initials, badges "Firma", "Proverena firma" (`verified_at`) and "Telefon proveren".
  - Activity line, plus facts: active count, address, "Na sajtu od", profile views (from 10) and live hours status.
  - Actions: Pozovi, WhatsApp, Viber and Poruka.
  - Listings come first in the HTML. The aside is `<details>` "O firmi i kontakt" with the boxes O firmi, Kontakt, Radno vreme, Mapa (a Google Maps search link, no API key) and Mreže. On desktop the aside is always open and sits on the left; below 768 px JS collapses it.
  - Mobile sticky bar: Pozovi firmu, Poruka and WhatsApp.
  - In the WordPress version the firm phone is shown openly on the profile, with no reveal step; this is kept.
- Owner and staff also see:
  - "Dopunite profil" hints for empty fields, linking to `config('listing.profile_edit_path')`.
  - A 30-day statistics box when the firm has Biznis.
- Private profile: initials, "Privatno lice", meta, Pozovi and Poruka, then the listings. It is `noindex` (SEO module).
- Firms without Biznis are `noindex` too (SEO module rule).
- Listings:
  - Category tabs are links (`?kategorija={top-level slug}`), shown only when there is more than one category. An unknown slug gives 404.
  - 24 per page, `?page=N`, at most 50 pages. A page out of range gives 404.
- A blocked user gives 404 to the public; a deleted user gives 410.

## API (`/api/v1`)

| Route | Auth | Response | Cache |
| --- | --- | --- | --- |
| `GET /listings/{id}` | optional bearer | `Listing` (card + detail fields) | `public, max-age=60` + ETag (views excluded from the ETag); `private, no-cache` with a token |
| `GET /listings/{id}/similar` | none | `ListingCard[]`, up to 10: same category tree, then parent | `public, max-age=300` |
| `POST /listings/{id}/contact` | required | `{phone, phone_verified}`, counted as a reveal | `private, no-store` |
| `GET /firms/{id}` | none | `Firm` | `public, max-age=300` |
| `POST /reports` | required | 204; 422 for an invalid body or self-report; 404 for a missing target | — |

The rate limiters are:

- `listing-api`: 120/min per IP.
- `listing-phone`: 10/min and 100/day per user or IP, on the web reveal and the API contact.
- `listing-report`: 5 per 10 min and 20/day.

On the API or with JSON, a limited request gets 429 `too_many_requests`; a web form gets a redirect back with a flash message.

`ListingFormatter::card()` and `detail()` are the stable entry points for other modules. Eager-load `ListingFormatter::CARD_RELATIONS` / `DETAIL_RELATIONS` first.

## Caching

| Key | TTL | Cleared |
| --- | --- | --- |
| `listing.seller_count.{user}` | 10 min | listing saved, deleted or restored |
| `listing.profile_tabs.{user}` (per category counts) | 10 min | same |
| `listing.similar.{id}.{limit}`, `listing.similar_page.{id}` | 10 min | TTL only |
| `listing.user_totals.{user}.{days}` (firm statistics) | 10 min | TTL only |

## Scale (200,000 listings, 2,000,000 daily stats rows)

Setup:

- Seeded in the dev database: 200k listings, 30% of them in one big category (Automobili, about 60k); 3,000 users; one firm with 5,000 listings; 10 days of stats for every listing.
- MariaDB 10.11 in the container, `php artisan serve`, config, route and view caches on.
- Medians of 5 runs with the application cache flushed before each run (so these are cold-cache query times).

| Path | Median | Notes |
| --- | --- | --- |
| similar, API, big category | 3.7 ms | UNION ALL of one LIMITed scan per subcategory on `(status, category_id, bumped_at)`; featured via `featured_until` |
| similar, API, leaf category | 4.7 ms | |
| similar, page (category chain) | 7.6 ms | |
| profile page 1 / page 50 (24 cards) | 1.4 / 5.4 ms | `(user_id, status, bumped_at)`, OFFSET at most 1,176 |
| profile tab counts, firm with 5,000 listings | 11.5 ms | then cached |
| seller active count, firm with 5,000 listings | 13.7 ms | then cached |
| view or reveal increment + daily upsert | 1.1 ms | atomic |
| firm 30-day totals, 5,000 listings × 10 days | 310 ms | 5,000 primary-key lookups; owner/staff-only box, cached 10 min |

Warm TTFB (full page, dev server):

| Page | TTFB |
| --- | --- |
| `/oglas/{slug}/` | 45–49 ms |
| `/korisnik/{firm}/` | 42 ms |
| `/korisnik/{firm}/?page=50` | 38 ms |
| `GET /api/v1/listings/{id}` | 23 ms |
| `GET /api/v1/listings/{id}/similar` | 26 ms |

Decisions:

- Similar listings and profile lists sort by `bumped_at DESC, id DESC`. `bumped_at` is set on publish and on every bump, so the sort matches the indexes.
- Featured-first ordering on the similar block is a separate small query, never a `CASE` in `ORDER BY`.
- The profile total comes from the cached tab counts, so the paginator does no second `COUNT(*)`.
- Firm totals are the only slow cold path. If firms that large appear, add a nightly per-user rollup; for now the 10-minute cache is enough.

## Not in this module

- The listing card component `resources/views/components/listing-card.blade.php` belongs to the search module. The file here is a minimal stub so the pages and tests render; replace it on merge.
- Profile editing (`/moj-nalog/...`), messages, favourites and the admin side of reports are in their own modules.
- The importer does not yet fill `firms.activity`, `firms.phone`, `firms.whatsapp` or `firms.viber` from WordPress (`meta_activity`, `meta_phone`, `meta_whatsapp`, `meta_viber`).
