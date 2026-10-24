# Modul SEO

Preusmerenja sa starog sajta, 410, kosa crta na kraju URL-a, `<head>` (title, opis, canonical, robots, Open Graph, JSON-LD), sitemap, robots.txt, llms.txt i provera URL-ova iz Google Search Console-a.

Izvor pravila: `09-deploy-seo.md` (razvojni paket), mapa ključnih reči (šabloni za title, H1 i opis) i plan za vidljivost u pretragama.

## Fajlovi

| Gde | Šta |
| --- | --- |
| `config/seo.php` | kanonski domen, parametri za praćenje, strane naloga, strane za sitemap, fraze kategorija, odredišta starih adresa |
| `app/Http/Middleware/Redirects.php` | tabela `redirects` + pravila za stare adrese (`app/Support/Seo/LegacyUrls.php`) |
| `app/Http/Middleware/Gone.php` | 410 iz tabele `gone_urls` i za obrisane oglase i naloge |
| `app/Http/Middleware/TrailingSlash.php` | 301 na adresu sa kosom crtom |
| `app/Support/Seo.php` | podaci za `<head>` jedne strane |
| `app/Support/Seo/Sitemap.php` | pravljenje i keš sitemapa |
| `resources/views/partials/seo-head.blade.php` | ispis `<head>` dela |
| `routes/web/seo.php` | `/sitemap*.xml`, `/robots.txt`, `/llms.txt`, `/stranica-404/` |
| `app/Console/Commands/SeoProvera.php` | `php artisan seo:provera` |
| `app/Console/Commands/SeoSitemap.php` | `php artisan seo:sitemap` (raspored: svakog sata) |
| `resources/seo/llms.txt` | tekst za `/llms.txt`, bez izmena u odnosu na odobreni |

Middleware je globalni (`bootstrap/app.php`), pre rutiranja, pa važi i za adrese koje ne postoje. Redosled: **Redirects → Gone → TrailingSlash**. Tako se `/product/paket` (bez kose crte) odmah šalje na `/oglasi/`, bez međukoraka.

## Kako drugi moduli koriste SEO

Kontroler popuni podatke, a layout (`layouts.app`) ih sam ispiše preko `partials.seo-head`:

```php
use App\Support\Seo;

Seo::for($listing);                                   // strana oglasa
Seo::for($category);                                  // /oglasi/{kategorija}/
Seo::for($category, ['location' => $mesto]);          // /oglasi/{kategorija}/{mesto}/
Seo::for($category, ['count' => $ukupno]);            // broj oglasa, ako ga kontroler već ima (bez dodatnog upita)
Seo::for($user);                                      // /korisnik/{slug}/: firma ili privatni profil
Seo::for($user, ['activity' => 'Auto servis']);       // firma sa delatnošću u naslovu
Seo::get()->home();                                   // početna
Seo::get()->listings($ukupno);                        // /oglasi/
Seo::get()->page('Česta pitanja', 'Opis...', '/faq/')->faq([['q' => '...', 'a' => '...']]);
```

Pojedinačno (sve metode vraćaju isti objekat, mogu da se nižu):

`title()`, `description()`, `h1()`, `canonical('/putanja/')`, `withoutCanonical()`, `noindex()`, `index()`, `type('product')`, `image($url)`, `breadcrumbs([['Početna', '/'], ['Oglasi', '/oglasi/'], ['Naslov', null]])`, `breadcrumb($naziv, $putanja)`, `schema([...])`, `faq([...])`, `meta('og:...', '...')`.

H1 koji odgovara šablonu: `Seo::get()->getH1()` (u šablonu: `<h1>{{ \App\Support\Seo::get()->getH1() }}</h1>`). Strana mora da ima tačno jedan H1.

Stari način i dalje radi: promenljive šablona `$title`, `$description`, `$canonical`, `$robots` (ako sadrži `noindex`) imaju prednost nad podacima iz `Seo`.

Pomoćne: `Seo::activeListings()` (objavljeni i neistekli oglasi), `Seo::isActive($listing)`, `Seo::countLabel(12)` → „12 oglasa”, `Seo::placeMinListings()`.

## Šabloni (tekstovi sa stare verzije)

Svi naslovi i opisi su u `resources/stranice/tekstovi.php` pod `seo.*` i menjaju se iz podešavanja (`page_text.seo.…`), bez izmene koda. Stalne strane imaju doslovne tekstove sa stare verzije (Yoast izvoz), ostale šablone.

| Strana | Title | H1 | Opis |
| --- | --- | --- | --- |
| Početna | `seo.home.title` + „ \| loglasi.rs” | iz početne | `seo.home.description` (doslovno, ne seče se) |
| /oglasi/ | Svi oglasi Loznica \| loglasi.rs | Svi oglasi u Loznici | `seo.archive.description` |
| Kategorija | `seo_title` ili „{kategorija}, oglasi Loznica \| loglasi.rs” | `seo_h1` ili „{kategorija} u Loznici” | `seo_description` (doslovno), pa opis termina, pa šablon |
| Oglas | {naslov}, {kategorija}, {mesto} (preko 70 znakova bez kategorije) | naslov | tekst oglasa od 60 znakova, inače šablon sa cenom |
| Firma | {firma}, {mesto} | firma | opis firme ili šablon |
| Privatni profil | {ime}, oglasi | ime | šablon |
| Pretraga | Pretraga: {upit} | | |
| Stalne strane (o nama, kontakt, faq, uslovi, privatnost, brisanje, cenovnik, za firme) | `seo.{strana}.title` | | `seo.{strana}.description` |
| 404 | Stranica nije pronađena \| loglasi.rs | | |

- {kategorija} kod potkategorije je naziv bez prefiksa roditelja („Automobili | Hečbek” → „Hečbek”).
- Strana 2 i dalje: „, strana N” pre sufiksa.
- Kategorija ima polja `seo_h1`, `seo_intro` (uvodni pasus na strani 1) i `seo_faq` (pitanja ispod liste, FAQPage). Popunjena su iz `database/seeders/data/category-seo-2026-10-24.php` (migracija `seo_category_landing_fields`, samo prazna polja).

## Pravila

### 1. Preusmerenja (301, jedan skok)

Opšte: odredište je uvek konačno (lanac iz tabele se razrešava pre slanja), apsolutni URL na domenu zahteva, sa kosom crtom na kraju. Parametri za praćenje se odbacuju: `utm_*`, `fbclid`, `gclid`, `gbraid`, `wbraid`, `msclkid`, `ttclid`, `igshid`, `mc_cid`, `mc_eid`, `_gl`, `nc`. Svaki pogodak se broji (`redirects.hits`, `seo_rule_hits`).

Ne diraju se `/api/*`, `/admin*`, `/up`.

1. **Tabela `redirects`** (admin): `from_path` → `to_path`, status iz reda (301, 302, 307, 308; sve drugo = 301). Poređenje bez obzira na kosu crtu na kraju i velika slova. Zapis može da ima i upit (`/?p=123`); tada se upit troši. Zapis samo sa putanjom prenosi ostale parametre (bez praćenja). Tabela ima prednost nad pravilima ispod.
2. `/wp-json/loglasi/v1/{putanja}` → `/api/v1/{putanja}`, **308** (isti metod i telo), ceo upit ostaje.
3. Stari sitemapovi → `/sitemap.xml`: `/sitemap_index.xml`, svaki `/{ime}-sitemap{N}.xml` (Yoast: `page-sitemap.xml`, `listivo_listing-sitemap2.xml`, `listivo_14-sitemap.xml`, `listivo_1910…1913`, `4657`, `4661`, `945`, `946`, `9145…9147`, `listivo_template`, `product`, `product_cat`), `/wp-sitemap.xml` i `/wp-sitemap-*.xml`.
4. `/wp-admin` i sve ispod → `/admin/`; `/wp-login.php` → `/login-registracija/`.
5. WooCommerce (po SEO dokumentu, odeljak 7): proizvodi, `/shop/`, `/cart/`, `/checkout/…`, `/naplata/…` → `/cenovnik/`; `/my-account/…` → `/moj-nalog/`.
5a. Stari panel: `/panel/…` → `/moj-nalog/`; `?tab=create` → `/postavi-oglas/`, `messages` → `/moj-nalog/poruke/`, `listings` → `/moj-nalog/oglasi/`, `favorites` → `/moj-nalog/omiljeni/`, `business` → `/za-firme/` (`config('seo.panel_tabs')`).
5b. Kategorije sa zarezom: `/oglasi/a,b/` i `?kategorija=a,b` → poslednja aktivna kategorija (`/oglasi/{slug}/`), inače `/oglasi/`. `?paginacija=N` → `/…/page/N/`.
6. Feeds: the site has none. Every URL ending in `/feed/` (`/feed/`, `/comments/feed/`, `/oglas/{slug}/feed/`, `/oglasi/.../feed/`, `/korisnik/.../feed/`) returns 410 from the Gone middleware.
7. `/oglasi/?kategorija={slug}`: ako je `kategorija` jedini parametar (pored onih za praćenje) i kategorija postoji i aktivna je → `/oglasi/{slug}/`; nepostojeća ili ugašena → `/oglasi/`. Sa drugim filterima nema preusmerenja (200, noindex, canonical na čistu stranu). Isto i za `/oglasi?kategorija=` bez kose crte (jedan skok).
8. `/oglasi/?marka-automobila={marka}` (sam ili uz `kategorija`) → `/oglasi/automobili/`.
9. `/?attachment_id={N}` → oglas kome slika pripada (`listing_images.wp_attachment_id`).
10. `/?p={N}` (i `&post_type=…`) → oglas sa tim WordPress ID-jem (`listings.wp_id`).
11. **Kosa crta:** GET/HEAD bez kose crte na kraju → 301 na istu adresu sa kosom crtom, upit ostaje isti. Ne važi za `/`, `/api/*`, `/admin*`, `/up`, fajlove (poslednji deo putanje ima ekstenziju: `.xml`, `.txt`, `.jpg`…), `/storage/*`, `/build/*`, `/livewire/*`.

### 2. 410 (trajno uklonjeno)

1. Svaka putanja iz tabele `gone_urls` (sa ili bez kose crte, upit se ne gleda) → 410, `X-Robots-Tag: noindex`, broji se pogodak. Šablon `errors.410` ako postoji, inače kratak tekst „Ova strana je trajno uklonjena.”
2. `/oglas/{slug}/` koji bi bio 404, a oglas postoji kao obrisan (soft delete) → 410.
3. `/korisnik/{slug}/` koji bi bio 404, a nalog postoji kao obrisan → 410.

Keš tabela `redirects` i `gone_urls` briše se sam na svaku izmenu reda (preko modela). Ako se tabela puni direktno SQL-om (uvoz), posle toga: `php artisan cache:forget seo.redirects.map` i `seo.gone.map`.

### 3. Robots i canonical

`<meta name="robots">` je `index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1` ili `noindex, follow`. Canonical je uvek na `https://loglasi.rs` (`SEO_BASE_URL`), sa kosom crtom, bez parametara za praćenje.

| Slučaj | robots | canonical |
| --- | --- | --- |
| Obična strana | index | na sebe |
| Filter, sortiranje, ključna reč, pretraga `/?s=` (bilo koji parametar osim praćenja i paginacije) | noindex | na čistu stranu (bez upita) |
| Paginacija `?page=N`, `?paged=N`, `/page/N/` | index | na samu sebe (sa brojem strane) |
| Strane naloga (`config('seo.noindex_paths')`: prijava, registracija, login-registracija, nalog, moj-nalog, panel, postavi-oglas, poruke, omiljeni, uporedite-oglase, brisanje-podataka, plaćanje…) | noindex | nema |
| Istekao oglas (status `expired` ili `expires_at` prošao) ili obrisan | noindex | nema |
| Kategorija bez aktivnih oglasa, `/oglasi/` bez oglasa | noindex | nema |
| Kategorija + mesto sa manje od 3 aktivna oglasa (prag: podešavanje `seo_place_min_listings`) | noindex | nema |
| Privatni profil `/korisnik/{slug}/` | noindex | nema |
| Firma bez paketa, opisa, logoa i aktivnog oglasa (`Seo::firmIndexable`) | noindex | nema |
| Firma sa paketom, opisom, logoom ili bar jednim aktivnim oglasom | index | na sebe |

Pravilo: noindex strana nema canonical, osim filtera (na čistu stranu). Paginacija se indeksira (SEO dokument; za-laravel.md 5.3 je tražio noindex, važi SEO dokument).

### 4. Open Graph i JSON-LD

- `og:locale`, `og:site_name`, `og:type` (website, product, profile), `og:title`, `og:description`, `og:url`, `og:image`; Twitter kartica `summary_large_image` kad postoji slika. Za oglas i `product:price:amount` / `product:price:currency`.
- Na svakoj strani jedan `@graph` sa `@id` čvorovima: **Organization** (`#organization`: logo `#logo`, email i telefon u E.164 iz podešavanja, adresa 15300 Loznica, `areaServed` 6 mesta, `sameAs` mreže), **WebSite** (`#website`, `sr-RS`, **SearchAction**), strana (`#webpage`: WebPage, CollectionPage ili ProfilePage) i **BreadcrumbList** (`#breadcrumb`). Kategorija na strani 1 ima i **ItemList** (12 oglasa).
- **BreadcrumbList** kad strana ima bar dva nivoa.
- Oglas: `og:type` article. **Product** + **Offer** samo uz cenu veću od 0 i van kategorija usluge i poslovi (valuta iz oglasa, `areaServed`, stanje iz polja oglasa). Oglas „Dogovor” nema Product.
- Firma: **LocalBusiness** (adresa, mesto, telefon u E.164, izostavlja se kad nije ispravan, logo, koordinate, sajt i mreže u `sameAs`, radno vreme ako je u obliku `{"mon": "08:00-16:00"}` ili `[{"day","open","close"}]`).
- **FAQPage**: `Seo::get()->faq([...])` na /faq/ i kategorijama sa pitanjima.
- JSON se ispisuje sa `JSON_HEX_TAG`, pa sadržaj oglasa ne može da zatvori `<script>`.

### 5. Sitemap

| Adresa | Sadržaj |
| --- | --- |
| `/sitemap.xml` | indeks svih delova sa `lastmod` |
| `/sitemap-strane.xml` | `config('seo.pages')`: /, /oglasi/, /o-nama/, /faq/, /kontakt/, /cenovnik/, /za-firme/, /uslovi-koriscenja/, /politika-privatnosti/ |
| `/sitemap-kategorije.xml` | aktivne kategorije sa bar jednim aktivnim oglasom (računaju se i oglasi potkategorija) |
| `/sitemap-mesta.xml` | `/oglasi/{kategorija}/{mesto}/` samo od 3 aktivna oglasa (isti prag kao za noindex); 404 dok nema nijedne |
| `/sitemap-firme.xml` | aktivni nalozi firmi koje se indeksiraju (isto pravilo kao `firmIndexable`); 404 dok nema nijedne |
| `/sitemap-oglasi-{N}.xml` | objavljeni i neistekli oglasi, po 1.000 (`seo.sitemap.per_file`), `lastmod` = poslednja izmena oglasa |

Ne ulaze: istekli, nacrti, na čekanju, odbijeni i obrisani oglasi, privatni profili, prazne kategorije, strane naloga, feed, llms.txt, ništa sa 410 liste.

`Content-Type: application/xml; charset=UTF-8`, `Cache-Control: public, max-age=3600`, bez sesije i kolačića. XML se kešira; keš se briše kad se promeni oglas (status, slug, naslov, opis, cena, kategorija, mesto, rok), kategorija, mesto, firma ili nalog (slug, tip, status). Pregledi i otkrivanja telefona ne brišu keš. `php artisan seo:sitemap` pravi sve iznova i zakazan je svakog sata (za oglase kojima je rok istekao bez izmene u bazi). Na serveru mora da radi `php artisan schedule:run` iz cron-a.

### 6. robots.txt, llms.txt, 404

- `/robots.txt`: na produkciji (`SEO_INDEXABLE=true`) tačan blok iz SEO dokumenta: grupa `*` sa `Disallow` za /admin, /api/, /panel/, /nalog/, /naplata/, /uporedite-oglase/ i `/*?*kljucna-rec=`, zatim imenovani botovi (Googlebot, Bingbot, OAI-SearchBot, ChatGPT-User, GPTBot, ClaudeBot, Claude-SearchBot, Claude-User, PerplexityBot, Google-Extended, Applebot-Extended) sa `Allow: /` i istim zabranama, pa `Sitemap: https://loglasi.rs/sitemap.xml`. Van produkcije: `Disallow: /` za sve. Fizički `public/robots.txt` je uklonjen da ne zaseni rutu.
- `/llms.txt`: tekst iz `resources/seo/llms.txt` (linkovi kategorija samo za postojeće vidljive kategorije, adresa iz `SEO_BASE_URL`), `text/plain; charset=utf-8`, keš 1 dan, nije u sitemapu.
- `/feed/` and every other feed URL: 410 (SEO decision 2026-10-24).
- `/stranica-404/` vraća pravi 404. Nepostojeće adrese su 404, nikad preusmerenje na početnu.

### 6a. Google tag i Search Console

- Podešavanja `google_tag_id` (GT-MKTJD4NV), `ga4_measurement_id` (G-EKVEVJTR90) i `google_site_verification` (prazno). Partial `partials/google`.
- gtag se učitava samo na produkciji i samo za ispravan ID (`GT-`, `G-`, `AW-`). Consent mode: podrazumevano odbijeno, traka za kolačiće (`partials/cookie-bar`, „Prihvatam” / „Odbijam”) čuva izbor u `localStorage` (`lg-kolacici`).
- `<meta name="google-site-verification">` samo kad je polje popunjeno.

### 7. HTTPS i www

`http://` → `https://` i `www.loglasi.rs` → `loglasi.rs` radi web server (jedno 301), ne aplikacija. Aplikacija preusmerava na domen zahteva, da lokalna provera ne skače na živi sajt.

## Provera URL-ova iz GSC-a

```bash
php artisan seo:provera raw-indeksirane.tsv http://loglasirs.local --csv=rezultat-$(date +%Y%m%d-%H%M).csv
php artisan seo:provera raw-indeksirane.tsv https://loglasi.rs --sitemap --gone --samo-greske
```

- Čita TSV (prva kolona URL), preskače zaglavlje i duplikate, domen zamenjuje adresom iz drugog argumenta.
- Za svaki URL određuje obrazac i očekivano ponašanje iz baze nove verzije (tabela 9.2): 200 + index / noindex, 301 na odredište, 308 za stari API, 404 za /stranica-404/, 410 za 410 listu i obrisane. Oglas, kategorija ili nalog koji ne postoje ni u bazi ni na 410 listi = „ručna provera” (greška).
- Zahtev ide bez praćenja preusmerenja, sa Googlebot User-Agent-om. Kod 3xx proverava odredište i prati ga još jednom: odredište mora da vrati 200 (inače „lanac preusmerenja” ili „odredište nije 200”). Kod 200 čita `<meta name="robots">`, `X-Robots-Tag` i canonical: indeksabilna strana mora biti bez noindex i sa canonical-om na sebe na `https://loglasi.rs`.
- `--sitemap`: svi URL-ovi iz `/sitemap.xml` moraju biti 200, indeksabilni i sa canonical-om na sebe. `--gone`: svaka adresa iz `gone_urls` mora da vrati 410 i ne sme biti u sitemapu.
- `--bez-kesa` dodaje `?nc=` (zaobilaženje keša), `--pauza=0.3` između zahteva, `--samo-greske` skraćuje tabelu.
- Na ekran: tabela i zbir (ukupno, OK, GREŠKA, 5xx, neočekivan 404, lanci). Izlazni kod 0 samo kad je sve OK.

## Otvorena pitanja za vlasnika

1. `/oglasi/?marka-automobila=` sada vodi na `/oglasi/automobili/`; ako budu strane marki, menja se `seo.targets.marka_automobila` ili pravilo.
2. Stari `/product/` URL-ovi vode na `/oglasi/`; kad bude strana „Za firme”, može `seo.targets.woo_product = '/za-firme/'`.
3. `/cart/`, `/checkout/`, `/my-account/` vode na početnu (Woo se ne prenosi).
4. ~~`/login-registracija/`: strana ili 301?~~ Rešeno: `/login-registracija/` je jedina strana za prijavu (stara adresa), `GET /prijava/` i `/registracija/` su 301 na njene tabove.
5. Tačni slugovi strana uslova i privatnosti (`/uslovi-koriscenja/`, `/politika-privatnosti/` su pretpostavka u `config('seo.pages')`).
