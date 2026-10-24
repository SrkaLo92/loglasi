# Katalog: kategorije, mesta, polja, cene

Modul daje stablo kategorija sa brojem oglasa, mesta, dodatna polja po kategoriji (marka, kilometraža, kvadratura...) i cenovnik, i to na dva načina: kao servise za druge module i kao API za mobilnu aplikaciju. Ovde su i zajednički pomoćnici za API (oblik greške, keš zaglavlja, ETag).

## Fajlovi

| Fajl | Šta |
| --- | --- |
| `app/Services/Catalog.php` | stablo, brojevi oglasa, mrvice, potkategorije, polja po kategoriji, mesta, cene; keš |
| `app/Services/ListingFields.php` | pravila za formu, provera i upis vrednosti polja, prikaz, filteri za pretragu |
| `app/Http/Api/ApiResponse.php` | JSON odgovor, Cache-Control, ETag/304, greške za sve rute pod `/api` |
| `app/Http/Api/ApiError.php` | izuzetak koji postaje greška po ugovoru |
| `app/Http/Api/CatalogFormatter.php` | oblici `Category`, `Location`, `Price` iz ugovora |
| `app/Http/Controllers/Katalog/CatalogApiController.php`, `routes/api/katalog.php` | API rute |
| `app/Providers/KatalogServiceProvider.php` | servisi, brisanje keša na događaje modela, ograničenje `katalog` |
| `database/seeders/KatalogSeeder.php` | kategorije, mesta i polja za lokalni razvoj |
| `tests/Feature/Katalog/` | testovi |

Izmene u zajedničkim fajlovima: `bootstrap/app.php` (jedan red, `ApiResponse::register`), `bootstrap/providers.php` (provajder), `DatabaseSeeder` (poziva `KatalogSeeder` samo lokalno), `FieldType` dobio `Year` (`year` postoji u ugovoru), `Category` dobio `url()` i `iconUrl()`.

## API (`/api/v1`)

| Ruta | Odgovor | Keš |
| --- | --- | --- |
| `GET /categories` | stablo `Category[]`: `id, name, slug, icon, count, children`; samo aktivne kategorije (neaktivna sakriva i svoje potkategorije) | `public, max-age=300` + ETag |
| `GET /locations` | `Location[]`: `id, name, slug`, po `sort` pa po nazivu | isto |
| `GET /fields?category=ID` | `Field[]`: `key, label, type, required, unit, depends_on, options, filter`. Za kategoriju poslednjeg nivoa to su polja forme; za roditelja (npr. „Automobili”) polja potkategorija za filtere, sva neobavezna | isto |
| `GET /categories/{id}/fields` | isto kao gore (REST oblik adrese) | isto |
| `GET /prices` | `Price[]`: `product, label, amount` (RSD), samo aktivni proizvodi | isto |

- `count` = broj objavljenih oglasa (`status = published`, bez obrisanih) u kategoriji i svim potkategorijama. Oglas ima jednu kategoriju, pa se u roditelju broji jednom.
- `icon`: pun URL ako je u `icon_path` upisan URL (uvoz), inače fajl na disku `public`; bez ikonice `null`.
- `filter` je u obliku iz ugovora: `none | exact | multi | range` (u bazi se jedan izbor zove `select`, u API-ju `exact`).
- `product`: `bump`, `featured_7` → `featured_7d`, `featured_30` → `featured_30d`, `biznis_12m` → `business_year`. Proizvod sa drugom šifrom se ne vraća dok se ne doda u ugovor (`CatalogFormatter::PRODUCT_CODES`).
- Ograničenje: 120 zahteva u minuti po IP adresi (`throttle:katalog`).

Greške (isto kao na WordPress-u):

```json
{"code":"missing_param","message":"Nedostaje obavezan parametar.","fields":{"category":"Obavezno polje."}}
{"code":"invalid_param","message":"Neispravni parametri.","fields":{"category":"Mora biti pozitivan ceo broj."}}
{"code":"not_found","message":"Kategorija ne postoji."}
{"code":"not_found","message":"Tražena adresa ne postoji."}
```

`/categories/abc/fields` nije ruta (404 „Tražena adresa ne postoji.”), `/categories/0/fields` daje 400 sa `fields.id`. Neaktivna kategorija se ponaša kao da ne postoji.

## API pomoćnici (za sve API module)

### Uspešan odgovor

```php
use App\Http\Api\ApiResponse;

return ApiResponse::json($request, $data);                                  // public, max-age=60
return ApiResponse::json($request, $data, ApiResponse::CACHE_CATALOG);      // public, max-age=300
return ApiResponse::json($request, $data, ApiResponse::CACHE_PRIVATE);      // za prijavljenog korisnika
return ApiResponse::json($request, $data, null, 201);                       // upis
```

- JSON bez `\u` i `\/` escape-a (`ApiResponse::JSON_FLAGS`).
- Za GET/HEAD sa statusom 200 dodaje slab ETag `W/"md5(tela)"`; kad `If-None-Match` sadrži isti ETag (i bez `W/`, više vrednosti zarezom, ili `*`) vraća 304 bez tela, sa istim `ETag` i `Cache-Control`.
- Odgovor koji zavisi od prijavljenog korisnika obavezno sa `CACHE_PRIVATE`.
- Kad ETag ne sme da zavisi od celog tela (npr. broj pregleda), proslediti svoj: `ApiResponse::json($request, $data, ApiResponse::CACHE_DEFAULT, headers: ['ETag' => ApiResponse::etag(json_encode($stabilniDeo))])`.

### Greške

Bacati `ApiError`, sve ostalo pretvara `ApiResponse::register` (uključeno u `bootstrap/app.php` za `api/*`):

```php
use App\Http\Api\ApiError;

throw new ApiError('limit_reached', 'Potrošili ste mesečni limit oglasa.', 403);
throw ApiError::invalidParams(['per_page' => 'Broj oglasa po stranici mora biti od 1 do 50.']);
throw ApiError::missingParams('category');
throw ApiError::notFound('Oglas ne postoji ili više nije aktivan.');
throw ApiError::forbidden();
```

Šta se samo pretvara:

| Izuzetak | Status | `code` | `message` |
| --- | --- | --- | --- |
| `ValidationException` na GET | 400 | `invalid_param` | Neispravni parametri. (`fields` = prva poruka po polju) |
| `ValidationException` na POST/PATCH/PUT/DELETE | 422 | `validation_failed` | Proverite unete podatke. |
| neprijavljen (`AuthenticationException`) | 401 | `unauthorized` | Potrebna je prijava. |
| Policy / `AuthorizationException` | 403 | `forbidden` | Nemate pravo pristupa. |
| nepoznata ruta, `ModelNotFoundException` | 404 | `not_found` | Tražena adresa ne postoji. |
| pogrešna metoda | 405 | `method_not_allowed` | Ova metoda nije dozvoljena za ovu adresu. |
| `throttle` | 429 | `too_many_requests` | Previše pokušaja. Pokušajte ponovo malo kasnije. (+ `Retry-After`) |
| sve ostalo | 500 | `server_error` | Došlo je do greške. Pokušajte ponovo. (bez detalja) |

`fields` se šalje samo kad nije prazno. Sve greške imaju `Cache-Control: no-store`. Za `token_expired` i slične posebne kodove modul naloga baca svoj `ApiError`.

### Oblici iz ugovora

`CatalogFormatter::categoryRef($category)` (kartica oglasa: `id, name, slug`), `locationRef($location)`, `price($product)`, `productCode('featured_7')`.

## Servis `Catalog`

`app(Catalog::class)` (singleton). Modeli koje vraća su samo za čitanje.

```php
$catalog->categories();                  // sve kategorije po ID-ju
$catalog->category(5); $catalog->categoryBySlug('automobili');
$catalog->categoryByPath('automobili');  // ili 'vozila/automobili'; samo aktivne, proverava lanac roditelja
$catalog->children($kategorija);         // direktna deca (null = koren)
$catalog->breadcrumbs($kategorija);      // od korena do kategorije (mrvice)
$catalog->ancestors($kategorija);        // isto bez same kategorije
$catalog->descendantIds($kategorija);    // [id, id potkategorija...] za whereIn('category_id', ...)
$catalog->isVisible($kategorija);        // aktivna i svi roditelji aktivni
$catalog->tree();                        // [['category' => Category, 'count' => int, 'children' => [...]], ...]
$catalog->counts(); $catalog->count($kategorija);
$catalog->fieldsFor($kategorija);        // Field[] sa atributima required i position
$catalog->field('marka');
$catalog->locations(); $catalog->location(3); $catalog->locationBySlug('loznica');
$catalog->prices();                      // aktivni proizvodi
```

Polja kategorije: prvo nasleđena od korena naniže, pa sopstvena; unutar iste kategorije po `sort` iz `category_field`, pa po `sort` polja. Potkategorija može ponovo da veže nasleđeno polje i time promeni `required` (mesto u redosledu ostaje kao kod roditelja).

### Keš

| Ključ | Šta | Briše se |
| --- | --- | --- |
| `katalog.struktura` | kategorije, polja, veze kategorija–polje | `Category`/`Field` sačuvan ili obrisan; rezervni rok 1 h |
| `katalog.brojevi` | broj objavljenih oglasa po kategoriji | oglas napravljen, obrisan, vraćen, ili mu se promeni `status`/`category_id`; rezervni rok 10 min |
| `katalog.mesta` | mesta | `Location` sačuvan ili obrisan |
| `katalog.cene` | aktivni proizvodi | `Product` sačuvan ili obrisan |

Pregledi, podizanje i ostale izmene oglasa ne brišu brojeve. Događaji modela se ne okidaju kod:

- masovne izmene preko Query Builder-a (`Listing::where(...)->update([...])`, npr. istek oglasa u rasporedu) → posle toga `app(Catalog::class)->flushCounts()`;
- `attach()/sync()/detach()` na vezi kategorija–polje (admin) → posle toga `app(Catalog::class)->flush()`.

## Servis `ListingFields`

Vrednosti se čuvaju u `listing_field_values`: tekst i izbor jedan red (`value`), broj i godina i `value_num` (za opseg), više izbora jedan red po vrednosti, checkbox red sa `"1"` samo kad je označen.

### Forma (postavljanje i izmena oglasa)

```php
$fields = app(ListingFields::class);

// U FormRequest-u:
public function rules(): array { return [...osnovna_pravila, ...$fields->rules($kategorija)]; }
public function messages(): array { return $fields->messages($kategorija); }

// Ili direktno, vraća očišćene vrednosti ili baca ValidationException ("fields.marka" => "...")
$vrednosti = $fields->validate($kategorija, $request->input('fields', []));
$fields->save($oglas, $vrednosti);   // zamenjuje sve stare vrednosti oglasa
```

Pravila po tipu: tekst do 255 znakova; broj ≥ 0; godina ceo broj od 1900 do sledeće godine; izbor jedna od opcija; više izbora niz različitih opcija; checkbox `true/false/1/0` (u HTML formi `value="1"`). Obavezno polje iz `category_field.required`. Ključevi koji ne pripadaju kategoriji se odbacuju. Posle `validate()` proslediti rezultat `save()`-u; bez provere koristiti `normalize()`.

### Prikaz

```php
$oglasi->load('fieldValues');                 // za listu, bez N+1
$fields->values($oglas);  // [['key' => 'kilometraza', 'label' => 'Kilometraža', 'value' => '180000', 'display' => '180.000 km', 'type' => 'number', 'items' => []], ...]
                          // 'items' ima nazive za više izbora (oprema: ['ABS', 'Klima']), da se prikažu kao oznake; API `GET /listings/{id}` vraća u `fields[]` i `type` i `items`
$fields->value('stanje', $oglas);            // 'polovno'
$fields->condition($oglas);                   // 'novo' | 'polovno' | null (polje `condition` iz ugovora)
```

Prikaz: broj sa tačkom za hiljade i jedinicom („180.000 km”, „1.234,5 m²”), godina „2014.”, izbor po labeli opcije, više izbora labele odvojene zarezom, checkbox „Da”. Redosled je redosled polja kategorije; vrednosti polja koja više ne pripadaju kategoriji oglasa se ne prikazuju.

### Pretraga (`f[ključ]=vrednost`)

```php
$fields->filters($kategorija);   // polja sa filterom: key, label, type, filter (exact|multi|range), unit, options, params
$uslovi = $fields->parseFilters($kategorija, $request->query('f'));   // ValidationException pod ključem "f"
$fields->apply($upit, $uslovi);  // jedan WHERE EXISTS po polju, koristi indekse (field_id, value) i (field_id, value_num)
```

- `exact`: jedna vrednost; `multi`: više vrednosti zarezom (bilo koja od njih); `range`: `f[ključ_od]` i `f[ključ_do]`.
- Poruke kao na WordPress-u: „Nepoznat filter: boja.”, „Za „Marka” je dozvoljena jedna vrednost.”, „Neispravna vrednost za „Stanje”.”, „Za „Godište” se šalju brojevi kao _od i _do.”, „Filteri polja se šalju kao f[ključ]=vrednost.”. U API-ju to postaje 400 `invalid_param` sa `fields.f`.
- Checkbox filter prima samo `1`.
- Bez kategorije (`null`) nema polja, pa je svaki `f[...]` nepoznat filter.

### API oblik polja

`$fields->toApi($field)` vraća `Field` iz ugovora; `ListingFields::apiFilter(FieldFilter::Select)` vraća `exact`.

### Zavisna polja (model zavisi od marke)

- `depends_on` je ključ roditeljskog polja (`model_automobila` → `marka_automobila`), inače `null`.
- Svaka opcija zavisnog polja ima `parent` = vrednost opcije roditelja: `{"value": "audi-a4", "label": "A4", "parent": "audi"}`. Opcije nezavisnih polja nemaju `parent`.
- Forma: nuditi samo opcije čiji je `parent` izabrana vrednost roditelja; dok roditelj nije izabran, polje je zaključano („Prvo izaberite: marka”).
- Upis: model bez marke daje „Prvo izaberite polje „Marka”.”, model druge marke „Izabrana vrednost za „Model” ne pripada izabranoj vrednosti polja „Marka”.” (API 422, ključ `fields.model_automobila`).
- Pretraga: `f[model_automobila]` radi i bez marke; sajt bez izabrane marke prikazuje modele grupisane po marki.
- Brojevi se primaju kako ih ljudi kucaju: „180.000”, „180 000 km”, „1,6”, „2014.” (i u filterima `_od`/`_do`). Snaga u kW se prikazuje i u KS: „103 kW (140 KS)”.

## Definicija kataloga (`php artisan katalog:uredi`)

`database/seeders/data/catalog/`: `fields.php` (polja, tipovi, opcije, preimenovanje starih vrednosti), `links.php` (koja polja traži koja kategorija, po slugu, i koja su obavezna), `cars.php` (82 marke i 1864 modela po uzoru na polovniautomobili; WordPress marke i modeli i njihovi stari slugovi). Komanda:

- usklađuje polja (naziv, tip, filter, jedinica, `depends_on`, opcije, redosled) i veze kategorija–polje za sve kategorije (kategorija koju nijedno pravilo ne pominje nema polja);
- prenosi sačuvane vrednosti na nove opcije (WordPress `a4` → `audi-a4`, Moguća zamena Da/Ne → checkbox), dopunjuje marku iz modela, briše vrednosti kojih nema u opcijama, brojeve „214.000” svodi na 214000, briše godine koje nisu godine;
- skida naziv roditelja sa potkategorija („Automobili | Džip/SUV” → „Džip/SUV”);
- prenosi i sačuvane pretrage (`f[model_automobila]`).

`--dry-run` broji izmene bez upisa. Pokreće se sama na kraju `uvoz:wordpress` (isključuje se sa `UVOZ_CATALOG_DEFINITION=false`). Izmene ovih polja u adminu briše sledeće pokretanje; trajne izmene idu u fajlove. Admin za polje sa mnogo opcija (modeli) prikazuje tekst „vrednost | Naziv | roditelj”, red po opciji.

## Prikaz na sajtu

- Izbor kategorije pri postavljanju: pretraga (`?trazi=`, uživo preko JavaScript-a) nudi samo kategorije poslednjeg nivoa sa putanjom („Vozila › Automobili”); ispod je stablo jedan nivo po strani (`?grupa=`), vrh kao pločice sa ikonama.
- Korak „Detalji”: „Osnovni podaci” u dve kolone na širem ekranu. Izbor sa do 7 kratkih vrednosti je red dugmića (drugi dodir poništava), duži izbor otvara prozor sa pretragom (`public/js/pick.js` + `pick.css`: `select[data-pick]`, `data-pick="year"` za godine u mreži po decenijama, `data-pick-popular` za „Najtraženije”, vrednosti u `ListingFields::POPULAR`). Pravi `<select>` ostaje u formi, pa sve radi i bez JavaScript-a i sa zavisnim poljima (`fields.js`). Polje sa više izbora i više od 8 vrednosti (`data-pick-multi`) je grupa koja se skuplja: zaglavlje pokazuje izabrano, otvorena ima pretragu. Da/ne polja su prekidači pod „Dodatno”; km/cm³/m²/kg dobijaju tačke za hiljade, kW pokazuje KS.
- Filteri: prvih 6 polja kategorije su vidljiva, ostala su pod „Više filtera (N)” koje je otvoreno kad je neki od njih izabran; izbori i godište od/do koriste isti prozor sa pretragom, duge liste su grupe koje se skupljaju.
- Stranica oglasa: pojedinačne vrednosti u tabeli „Karakteristike”, polja sa više izbora kao oznake ispod svog naslova.

## Podaci za lokalni razvoj

`php artisan migrate --seed` (sa `APP_ENV=local`) pravi 24 glavne kategorije sa potkategorijama (slugovi sa sajta: `automobili`, `nekretnine`, `mobilni-telefoni`, `poslovi`, `usluge`, `besplatne-stvari`; ostali po nazivima sa početne: Bicikli i delovi, Kamioni, Moda, Nameštaj, Građevinarstvo, Poljoprivreda, Muzički instrumenti...), 19 mesta (Loznica, Banja Koviljača, Lešnica, Jadranska Lešnica, Tršić, Klupci, Lipnica i okolna sela) i polja:

- svuda gde se prodaje roba: Stanje (novo, polovno, oštećeno), Vrsta oglasa, Moguća zamena, Dostava;
- Automobili: Marka*, Model, Godište*, Kilometraža (km), Gorivo, Menjač, Kubikaža (cm³); Motocikli i Kamioni deo toga;
- Nekretnine: Kvadratura* (m²), Grejanje, Vrsta oglasa; Stanovi i Kuće još Broj soba (i Sprat za stanove);
- Mobilni telefoni: Marka telefona, Memorija; Poslovi: Radno vreme.

Pravi spisak kategorija i mesta dolazi uvozom iz WordPress-a (modul uvoza). Seeder može da se pokrene više puta (`php artisan db:seed --class=KatalogSeeder`).

## Testovi

`DB_DATABASE=lb_katalog_test php artisan test tests/Feature/Katalog`: sve rute, oblik odgovora, brojevi (potkategorija jednom, samo objavljeni, obrisani i odbijeni ne), sakrivanje neaktivnih, ETag i 304, brisanje keša (novi oglas, promena statusa i kategorije, brisanje i vraćanje, izmena kategorije, polja, mesta i cene; pregled ne briše), greške i ograničenje broja zahteva, pomoćnici kataloga (mrvice, putanja, potkategorije, petlja u stablu), provera, upis, prikaz i filteri polja, seeder.
