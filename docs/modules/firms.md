# Firms module

Firm directory, firm self-service, firm packages (price list of 24 Oct 2026) and their rules for the other modules.
The public firm profile `/korisnik/{slug}/` and `GET /firms/{id}` belong to the listing module.

## URLs

| Path | What | Route name |
| --- | --- | --- |
| `/firme/` | "Firme iz Loznice": firms with an active package; filters `mesto` (place slug) and `keyword` | `firms.directory` |
| `/moj-nalog/firma/` (GET, PUT) | firm profile form + package status box (firm accounts only, others 403) | `firms.account`, `firms.account.update` |
| `/moj-nalog/firma/statistika/` | statistics: `?period=30` (all packages), `90` (Pro, Max) | `firms.account.stats` |
| `GET/PATCH /api/v1/me/firm`, `GET /api/v1/me/firm/stats` | mobile contract; non-firm accounts get 404 `not_found` | — |

No URL for the directory was defined on the current site or in the keyword map (phrase "firme Loznica"), so `/firme/` was chosen; it is in `config('seo.pages')` (sitemap).
The home page firm section links to it as "Sve firme (N)" with the directory's own count.

## Packages

| Plan | `package` | Price (products, admin) | Active listings | Included each month | Statistics |
| --- | --- | --- | --- | --- | --- |
| Business Start | `start` | 1.490 / month, 14.900 / year | 20 | — | 30 days |
| Business Pro | `pro` | 2.990 / 29.900 | 100 | 10 bumps, 2 highlights | 30 + 90 days, per listing |
| Business Max | `max` | 4.990 / 49.900 | unlimited | 20 bumps, 5 highlights, 1 Premium XL | 30 + 90 days, per listing |
| Prvih 30 firmi | `free_program = true` | 0 for 12 months | unlimited | 5 bumps, 1 highlight | 30 days |

- Columns on `firms`: `package` (start/pro/max), `package_period` (monthly/yearly), `package_starts_at`, `package_ends_at`, `free_program`, `package_reminder_days`, `package_reminder_for`. `founding_free` stays as "took one of the 30 free places" (counter on /cenovnik/). `biznis_until` was dropped; the migration moved its values (free founding terms stay free, any other term became Business Start, yearly). Product `biznis_12m` is deactivated.
- The listing limit comes from the product row (`business_{package}_{period}.listing_limit`, editable in the admin), with `config('firms.packages')` as the fallback. `ListingLimits` uses it, so posting and `/me/limits` follow the package automatically. Without an active term a firm has the private limit (`private_active_limit`).
- Definitions (labels, credits, advanced statistics): `config/firms.php`. User-facing text never says "Founder"; the free plan is "Prvih 30 firmi" / "Besplatna prva godina".
- There is no automatic billing. Purchase and payment are owned by the payments module, which calls `FirmPackage::grant($firm, 'pro', 'yearly')`: the same running package is extended from its end, anything else starts now and ends the free year. The admin starts the free year with `FirmPackage::grantFreeYear($firm)`.

### Monthly credits

Table `firm_monthly_credits` (one row per firm per calendar month, Europe/Belgrade): granted and used bumps, highlights and Premium XL. The row is created on first use from the current package, an upgrade during the month raises the allowance, and unused credits do not carry over.

```php
FirmCredits::remaining($firm);          // ['bumps' => 7, 'highlights' => 2, 'premium_xl' => 0]
FirmCredits::consume($firm, 'bumps');   // atomic; false when nothing is left
```

Applying the promotion itself (bumped_at, featured_until) is the posting/payments module's job: consume a credit first and, if it returns true, apply the promotion without an order.

## FirmBenefits (for other modules)

`app(App\Services\FirmBenefits::class)` (singleton) takes a `User` or a `Firm`:

`hasPackage`, `packageCode` (start/pro/max/free/null), `packageLabel`, `isFreeProgram`, `packageEndsAt`, `daysLeft`, `isExpiringSoon`, `isExpired`, `listingLimit`, `monthlyCredits`, `canShowFirmBadge` (every firm account), `canShowLogo`, `canShowCover`, `canShowVerifiedBadge` (package + `verified_at` set by the admin), `canShowActivity`, `canShowDetails`, `canShowWorkingHours`, `canShowMap`, `canShowSocials`, `canShowWebsite`, `canShowMessengerButtons`, `canShowStickyCallBar`, `canShowProfileViews` (from 10 views), `canShowStats`, `canShowAdvancedStats`, `isIndexable`, `isListedInDirectory`, `all()` (all flags).

When a term ends the firm keeps all its data, the profile shows like a plain one, it leaves the directory and gets the private limit. Indexing of `/korisnik/{slug}/` follows `Seo::firmIndexable` (package, description, logo or an active listing).

## Directory

- Firm accounts that are active, not deleted and have `package_ends_at` in the future (paid or free year).
- Order: the first 30 firms first (`founding_free`), then by active listings, then by name. 24 per page, out-of-range pages are 404.
- Search folds case and diacritics ("ceda" finds "Čeda"). Filtered pages are noindex with canonical to `/firme/`; an empty directory is noindex.
- SEO: title "Firme iz Loznice – lokalne firme i usluge | loglasi.rs", H1 "Firme iz Loznice", BreadcrumbList and an ItemList of LocalBusiness entries for the page.
- Data: `FirmDirectory::all()` is one cached array (10 min). It is built with two queries: firms joined to users using `firms.package_ends_at`, then one grouped count of active listings using `listings(user_id, status, bumped_at)`. The cache is cleared when a firm, or a firm account's type, status, slug or name, changes.

## Self-service

- Fields: name, activity (≤60), description (≤600, no HTML), PIB (9 digits), MB (8), address, place, coordinates (pasted as "44.5331, 19.2258"), firm phone (E.164; empty = account phone), WhatsApp/Viber, website, Facebook, Instagram (http(s) only, "@name" becomes a full link, the network's own host only), working hours (7 text fields, ≤40 characters each; the same `{"1": "08–16"}` shape as the importer).
- Logo: WebP that fits inside 400 px. Cover: WebP cropped to 1600×500, never upscaled. Both are stored in `firms/{id}/`; the old file is deleted, and both can be removed.
- Package, dates, `free_program`, `founding_free` and `verified_at` are never taken from input (guarded, plus a test).
- The status box shows plan, monthly/yearly or free year, the end date, days left, credits left this month, active listings vs the limit, a pending order, and package prices from `products`. It links to `config('firms.renew_url')` (`/cenovnik/#lgc-firme` until the payments module has an order page).
- Rate limit `firms-account`: 20 changes per minute per user (web and API).
- The listing module's "Dopunite profil" hint for firm owners links here.

## Statistics

`FirmStats::daily($userId, $days)` and `perListing()` read `listing_daily_stats` joined to the firm's listings. The plan uses the listings `user_id` index, then the `(listing_id, date)` primary key range per listing, so the cost depends on the firm's own rows. Results are cached for 5 minutes per firm and period.
The web page has 4 total tiles, 4 inline SVG bar charts (one series each, native tooltips, no JS), a per-listing table (Pro/Max) and a per-day table.
API `period`: `today`, `7d` (default), `30d`; `daily[].value` = views. An invalid period gets 400 `invalid_param`; without a package the endpoint returns 403 `package_required`.

Measured: EXPLAIN on the dev database confirms the index choice for the stats join, the directory firm query (`firms_package_ends_at_index`) and the grouped listing count. Timings at 200k listings and millions of stats rows were not measured (not seeded, to save time). If a firm with thousands of listings gets slow, add a per-user daily rollup table.

## Reminders

`php artisan firms:package-reminders` runs daily at 09:00 (scheduled in `FirmsServiceProvider`). It queues the Serbian plain-text mail `PackageExpiringSoon`:
- yearly terms and the free year: 14 and 3 days before the end;
- monthly terms: 3 days before.

Each reminder is sent once per term, a renewal starts the series again, and a missed run sends only the closest reminder. Blocked accounts and accounts that are no longer firm accounts are skipped.

## Tests

`tests/Feature/Firms/`: directory (filters, order, SEO, cache, pagination, home link), account (access, saving and cleaning, guarded fields, images, expired/free/pending states), statistics, API, benefits, credits, reminders.

```bash
DB_DATABASE=lb_firms_test php artisan test tests/Feature/Firms
```
