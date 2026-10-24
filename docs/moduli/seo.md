# Modul SEO

Preusmerenja sa starog sajta, 410, kosa crta na kraju URL-a, `<head>` (title, opis, canonical, robots, Open Graph, JSON-LD), sitemap, robots.txt, llms.txt, feed i provera URL-ova iz Google Search Console-a.

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
| `routes/web/seo.php` | `/sitemap*.xml`, `/robots.txt`, `/llms.txt`, `/feed/`, `/stranica-404/` |
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

## Šabloni (mapa ključnih reči)

| Strana | Title (do 60 znakova) | H1 | Opis (do 155) |
| --- | --- | --- | --- |
| Početna | Oglasi Loznica – besplatni mali oglasi \| loglasi.rs | Oglasi za Loznicu i okolinu | Kupi i prodaj u Loznici: automobili, stanovi, posao, drva, poljoprivreda. Besplatno objavi oglas. |
| /oglasi/ | Svi oglasi u Loznici \| loglasi.rs | Svi oglasi u Loznici | {broj} oglasa iz Loznice i okoline: … |
| Kategorija | {Fraza} Loznica – {broj} oglasa \| loglasi.rs | {Fraza} u Loznici i okolini | {broj} oglasa za {frazu} u Loznici, Banji Koviljači i Lešnici. Pogledaj cene ili besplatno objavi oglas. |
| Kategorija + mesto | {Fraza} {Mesto} – oglasi \| loglasi.rs | {Fraza} – {Mesto} | {broj} oglasa za {frazu} u mestu {Mesto}, opština Loznica. |
| Oglas | {Naslov}, {Mesto} – {cena} \| loglasi.rs | {Naslov} | prvih 140 znakova opisa + (mesto) |
| Firma | {Firma} – {delatnost} Loznica \| loglasi.rs (bez delatnosti: „firma iz Loznice”) | {Firma} | {Delatnost} u Loznici: adresa, telefon i oglasi firme {Firma}. |
| Privatni profil | {Ime} – oglasi korisnika \| loglasi.rs | {Ime} | Oglasi korisnika {Ime} na loglasi.rs… |

- {Fraza} je narodni naziv iz `config('seo.phrases')` po slugu (automobili → „Polovni automobili”), inače naziv kategorije. Za opis se koristi akuzativ („za polovne automobile”); kad ga nema u konfiguraciji, piše se „u kategoriji {Naziv}”, da rečenica ostane gramatički tačna.
- `seo_title` i `seo_description` kategorije (iz admina) imaju prednost nad šablonom.
- Broj: „1 oglas”, „21 oglas”, „2 oglasa”, „12 oglasa”.
- Predugačak title: prvo otpada broj oglasa (kategorija), pa se skraćuje sadržaj; sufiks „| loglasi.rs” ostaje dok god staje u 60 znakova.

## Pravila

### 1. Preusmerenja (301, jedan skok)

Opšte: odredište je uvek konačno (lanac iz tabele se razrešava pre slanja), apsolutni URL na domenu zahteva, sa kosom crtom na kraju. Parametri za praćenje se odbacuju: `utm_*`, `fbclid`, `gclid`, `gbraid`, `wbraid`, `msclkid`, `ttclid`, `igshid`, `mc_cid`, `mc_eid`, `_gl`, `nc`. Svaki pogodak se broji (`redirects.hits`, `seo_rule_hits`).

Ne diraju se `/api/*`, `/admin*`, `/up`.

1. **Tabela `redirects`** (admin): `from_path` → `to_path`, status iz reda (301, 302, 307, 308; sve drugo = 301). Poređenje bez obzira na kosu crtu na kraju i velika slova. Zapis može da ima i upit (`/?p=123`); tada se upit troši. Zapis samo sa putanjom prenosi ostale parametre (bez praćenja). Tabela ima prednost nad pravilima ispod.
2. `/wp-json/loglasi/v1/{putanja}` → `/api/v1/{putanja}`, **308** (isti metod i telo), ceo upit ostaje.
3. Stari sitemapovi → `/sitemap.xml`: `/sitemap_index.xml`, svaki `/{ime}-sitemap{N}.xml` (Yoast: `page-sitemap.xml`, `listivo_listing-sitemap2.xml`, `listivo_14-sitemap.xml`, `listivo_1910…1913`, `4657`, `4661`, `945`, `946`, `9145…9147`, `listivo_template`, `product`, `product_cat`), `/wp-sitemap.xml` i `/wp-sitemap-*.xml`.
4. `/wp-admin` i sve ispod → `/admin/`; `/wp-login.php` → `/prijava/`.
5. WooCommerce: `/product/{slug}/` → `/oglasi/`; `/product-category/…`, `/product-tag/…`, `/shop/` → `/oglasi/`; `/cart/`, `/checkout/…`, `/my-account/…` → `/` (Woo se ne koristi, plaćanje je uplatom).
6. Feedovi: `/comments/feed/` → `/feed/`; `/oglas/{slug}/feed/` → `/oglas/{slug}/`; `/oglasi/{kategorija}/feed/` → `/oglasi/{kategorija}/`.
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

`<meta name="robots">` je `index, follow, max-image-preview:large` ili `noindex, follow`. Canonical je uvek na `https://loglasi.rs` (`SEO_BASE_URL`), sa kosom crtom, bez parametara za praćenje.

| Slučaj | robots | canonical |
| --- | --- | --- |
| Obična strana | index | na sebe |
| Filter, sortiranje, ključna reč, pretraga `/?s=` (bilo koji parametar osim praćenja i paginacije) | noindex | na čistu stranu (bez upita) |
| Paginacija `?page=N`, `?paged=N`, `/page/N/` | noindex | na samu sebe (sa brojem strane) |
| Strane naloga (`config('seo.noindex_paths')`: prijava, registracija, login-registracija, nalog, moj-nalog, panel, postavi-oglas, poruke, omiljeni, uporedite-oglase, brisanje-podataka, plaćanje…) | noindex | nema |
| Istekao oglas (status `expired` ili `expires_at` prošao) ili obrisan | noindex | nema |
| Kategorija bez aktivnih oglasa, `/oglasi/` bez oglasa | noindex | nema |
| Kategorija + mesto sa manje od 3 aktivna oglasa (prag: podešavanje `seo_place_min_listings`) | noindex | nema |
| Privatni profil `/korisnik/{slug}/` | noindex | nema |
| Firma bez aktivnog Biznis paketa | noindex | nema |
| Profil firme sa Biznis paketom | index | na sebe |

Pravilo: noindex strana nema canonical, osim paginacije (na sebe) i filtera (na čistu stranu), kako traži 09-deploy-seo.md.

### 4. Open Graph i JSON-LD

- `og:locale`, `og:site_name`, `og:type` (website, product, profile), `og:title`, `og:description`, `og:url`, `og:image`; Twitter kartica `summary_large_image` kad postoji slika. Za oglas i `product:price:amount` / `product:price:currency`.
- Na svakoj strani jedan `@graph`: **Organization** (ime, opis = jedina rečenica sajta, `areaServed` Loznica, `sameAs` Facebook, Instagram, TikTok, email) i **WebSite** sa **SearchAction** (`/oglasi/?keyword={search_term_string}`, `config('seo.search_url')`).
- **BreadcrumbList** kad strana ima bar dva nivoa.
- Oglas: **Product** + **Offer** (cena, valuta EUR/RSD iz oglasa, `InStock` / `Discontinued` za istekao, prodavac, mesto). Oglas „Dogovor” nema Offer.
- Firma: **LocalBusiness** (adresa, mesto, telefon, logo, koordinate, sajt i mreže u `sameAs`, radno vreme ako je u obliku `{"mon": "08:00-16:00"}` ili `[{"day","open","close"}]`).
- **FAQPage**: `Seo::get()->faq([...])` na /faq/ i kategorijama sa pitanjima.
- JSON se ispisuje sa `JSON_HEX_TAG`, pa sadržaj oglasa ne može da zatvori `<script>`.

### 5. Sitemap

| Adresa | Sadržaj |
| --- | --- |
| `/sitemap.xml` | indeks svih delova sa `lastmod` |
| `/sitemap-strane.xml` | `config('seo.pages')`: /, /oglasi/, /o-nama/, /faq/, /kontakt/, /cenovnik/, /za-firme/, /uslovi-koriscenja/, /politika-privatnosti/ |
| `/sitemap-kategorije.xml` | aktivne kategorije sa bar jednim aktivnim oglasom (računaju se i oglasi potkategorija) |
| `/sitemap-mesta.xml` | `/oglasi/{kategorija}/{mesto}/` samo od 3 aktivna oglasa (isti prag kao za noindex); 404 dok nema nijedne |
| `/sitemap-firme.xml` | aktivni nalozi firmi sa važećim Biznis paketom; 404 dok nema nijedne |
| `/sitemap-oglasi-{N}.xml` | objavljeni i neistekli oglasi, po 1.000 (`seo.sitemap.per_file`), `lastmod` = poslednja izmena oglasa |

Ne ulaze: istekli, nacrti, na čekanju, odbijeni i obrisani oglasi, privatni profili, prazne kategorije, strane naloga, feed, llms.txt, ništa sa 410 liste.

`Content-Type: application/xml; charset=UTF-8`, `Cache-Control: public, max-age=3600`, bez sesije i kolačića. XML se kešira; keš se briše kad se promeni oglas (status, slug, naslov, opis, cena, kategorija, mesto, rok), kategorija, mesto, firma ili nalog (slug, tip, status). Pregledi i otkrivanja telefona ne brišu keš. `php artisan seo:sitemap` pravi sve iznova i zakazan je svakog sata (za oglase kojima je rok istekao bez izmene u bazi). Na serveru mora da radi `php artisan schedule:run` iz cron-a.

### 6. robots.txt, llms.txt, feed, 404

- `/robots.txt`: na produkciji (`SEO_INDEXABLE=true`, podrazumevano kad je `APP_ENV=production`) `Disallow: /admin/` za sve, eksplicitno `Allow: /` za Googlebot, Bingbot, GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, Claude-SearchBot, Claude-User, PerplexityBot, Perplexity-User, Google-Extended, Applebot, Applebot-Extended, i `Sitemap: https://loglasi.rs/sitemap.xml`. Filteri, paginacija, API i strane naloga se ne blokiraju (Google mora da vidi noindex). Van produkcije: `Disallow: /` za sve. Fizički `public/robots.txt` je uklonjen da ne zaseni rutu.
- `/llms.txt`: tekst iz `resources/seo/llms.txt`, `text/plain; charset=utf-8`, keš 1 dan, nije u sitemapu.
- `/feed/`: RSS sa 30 najnovijih aktivnih oglasa, `X-Robots-Tag: noindex`.
- `/stranica-404/` vraća pravi 404. Nepostojeće adrese su 404, nikad preusmerenje na početnu.

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
4. `/login-registracija/` (link iz llms.txt): ostaje strana ili 301 na `/prijava/`? Odlučuje modul naloga; ako bude 301, dodati red u `redirects`.
5. Tačni slugovi strana uslova i privatnosti (`/uslovi-koriscenja/`, `/politika-privatnosti/` su pretpostavka u `config('seo.pages')`).
