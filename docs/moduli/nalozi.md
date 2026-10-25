# Modul „Nalozi”: prijava, registracija, „Moj nalog”, tokeni za aplikaciju

## Šta postoji

### Strane sajta (Blade, bez JS-a, sve imaju `noindex,follow`)

| Putanja | Šta radi |
| --- | --- |
| `/login-registracija/` | jedina strana za prijavu i registraciju (adresa sa starog sajta): jedna kartica, tabovi „Prijava” i „Novi nalog” (`?tab=prijava`, `?tab=registracija`; bez JS-a tabovi su linkovi). Posle neuspele forme otvara se njen tab (skriveno polje `_tab`). Linkovi: `LoginPage::url()` |
| `POST /prijava/` | prijava emailom i lozinkom, „Zapamti me”; `GET /prijava/` je 301 na `/login-registracija/?tab=prijava` |
| `POST /registracija/` | ime, email, telefon, lozinka dva puta, prihvatanje uslova; uvek nalog **privatnog lica**; `GET /registracija/` je 301 na `/login-registracija/?tab=registracija` |
| `POST /odjava/` | odjava |
| `/zaboravljena-lozinka/` (GET, POST) | šalje link mejlom; isti odgovor i kad nalog ne postoji |
| `/nova-lozinka/{token}/`, `POST /nova-lozinka/` | nova lozinka; posle promene odjavljuju se svi uređaji |
| `/potvrda-emaila/` | obaveštenje i dugme „Pošalji link ponovo” (`POST /potvrda-emaila/ponovo/`) |
| `/potvrda-emaila/{id}/{hash}` | potpisan link iz mejla (važi 60 min), radi i bez prijave |
| `/moj-nalog/` | podaci, mesto, profilna slika, lozinka, limit oglasa ovog meseca, link za brisanje |
| `PUT /moj-nalog/podaci/`, `PUT /moj-nalog/lozinka/`, `POST`/`DELETE /moj-nalog/slika/` | izmene |
| `/moj-nalog/brisanje/` (GET potvrda, DELETE) | brisanje naloga uz lozinku i potvrdu |

Imena ruta: `login`, `register`, `logout`, `password.*`, `verification.*`, `nalog`, `nalog.*`. Linkovi se prave sa `url_slash()`.
Javni profil `/korisnik/{slug}/` nije ovde (modul firmi).

Uslovi korišćenja i politika privatnosti su linkovani kao `/uslovi-koriscenja/` i `/politika-privatnosti/` (strane pravi modul stranica).

### Pravila

- Email se čuva malim slovima; prijava ne zavisi od velikih slova i razmaka.
- Telefon se čuva u E.164 (`App\Support\Phone`). Promena broja skida `phone_verified_at` (posmatrač `User::saving` u `NaloziServiceProvider`), osim kad admin u istom čuvanju upiše i proveru.
- Blokiran korisnik (`status = blocked`) ne može da se prijavi; ako je već prijavljen, `EnsureNotBlocked` (u `web` grupi) ga odjavljuje. Na API-ju njegov token prestaje da važi (`account_blocked`).
- Registracija ne prima `account_type`, `role` ni `status`: nalog firme i uloge postavlja samo administrator.
- Slug za `/korisnik/{slug}/` se pravi iz imena (đ → dj, kao WordPress za sr_RS) i uvek je jedinstven.
- Brisanje naloga: oglasi se meko brišu (stari URL-ovi daju 410), brišu se razgovori i poruke, omiljeni, sačuvane pretrage, uređaji, tokeni, profil firme i slika. Red korisnika ostaje bez ličnih podataka (ime „Obrisan korisnik”, nasumičan email, bez telefona i lozinke) i meko je obrisan, da narudžbine ostanu u knjigovodstvu. Isti email posle može ponovo da se registruje. (`App\Support\Auth\AccountDeletion`)

### Ograničenja broja zahteva

| Limiter | Gde | Koliko |
| --- | --- | --- |
| `nalozi-login` | `/prijava/`, `/auth/login` | 5/min po email + IP, 30/min po IP |
| `nalozi-register` | `/registracija/`, `/auth/register` | 5/sat po IP |
| `nalozi-password` | zaboravljena i nova lozinka, `/auth/password-reset` | 3 na 10 min po emailu, 10/sat po IP |
| `nalozi-refresh` | `/auth/refresh` | 30/min po IP |
| `nalozi-account` | izmene „Moj nalog”, `PATCH`/`DELETE /me` | 20/min po korisniku |

Na sajtu se prekoračenje prikazuje kao greška iznad forme, na API-ju kao 429 `too_many_requests`.

### Dozvole (za ostale module)

- Gate `admin` (samo administrator) i `staff` (administrator i moderator).
- Middleware `staff` i `staff:admin` (gost → prijava, ostali → 403), `not-blocked` (već uključen u `web`).
- `App\Policies\UserPolicy`: `update`, `delete`, `block`, `verifyPhone`, `changeAccountType` (samo admin), `changeRole`, `viewAny`.

### Zajednički alati

- `App\Support\Phone`: `normalize()` → E.164, `display()` „+381 65 541 5128”, `national()` „065 541 5128”, `mask()` „065 541 ••••”, `links()` (tel:, wa.me, viber://, sms:), `isMobile()`. Pravilo validacije: `App\Rules\PhoneNumber`.
- `App\Support\ListingLimits`: `for($user)`, `canCreate($user)`, `monthlyLimit($user)`. Privatno lice: podešavanje `private_monthly_limit`. Firma sa aktivnim Biznis paketom: `products.listing_limit` za `biznis_12m`, a prazno = bez ograničenja (API tada vraća 999 i `unlimited: true`). Firma bez aktivnog paketa ima limit privatnog lica. Broje se oglasi napravljeni u ovom kalendarskom mesecu (Europe/Belgrade), i obrisani.
- `App\Support\ApiError::response()` i `App\Exceptions\ApiExceptionRenderer`: sve greške na `/api/*` u obliku `{code, message, fields}` na srpskom.
- Prevodi validacije i poruka su u `lang/sr/`.

## Lozinke i WordPress

Nove lozinke se hešuju sa **argon2id** (drajver `loglasi`, `config/hashing.php`). Heševi preneti iz WordPress-a ostaju u koloni `password` kakvi jesu (cast `hashed` ih prepoznaje i ne dira):

| Oblik | Provera |
| --- | --- |
| `$wp$2y$...` (WP 6.8+) | `password_verify(base64(hmac_sha384(lozinka, "wp-sha384")), heš bez "$wp")` |
| `$P$...` / `$H$...` | phpass (prenosivi MD5), kao `class-phpass.php` |
| 32 heks znaka | stari MD5 |
| `$2y$...` | običan bcrypt |

Posle prve uspešne prijave (sajt ili aplikacija) heš se zamenjuje argon2id hešom. Uvoz treba samo da upiše `user_pass` u `password`. Kod: `App\Support\Auth\WordPressPassword`, `App\Support\Auth\LoglasiHasher`.

## Kako se mobilna aplikacija prijavljuje

Osnova: `/api/v1`. Ugovor: `api/openapi.yaml` iz repozitorijuma aplikacije.

1. `POST /auth/login` `{email, password, device_name?}` ili `POST /auth/register` `{name, email, phone?, password, accept_terms}` vraća
   `{access_token, refresh_token, expires_in: 900, user: Me}` (registracija sa 201).
2. Svaki zaštićen zahtev šalje `Authorization: Bearer <access_token>`. Access token je Sanctum token koji važi **15 minuta**.
3. Kad server vrati 401 sa `token_expired`, aplikacija šalje `POST /auth/refresh` `{refresh_token}` i dobija **nov par** tokena. Refresh token važi **30 dana**, čuva se samo kao SHA-256 heš u `refresh_tokens` i menja se pri svakom osvežavanju: stari odmah prestaje da važi, a stari access token tog uređaja se briše.
   Ako neko ponovo pošalje već zamenjen refresh token (npr. ukraden), opoziva se ceo lanac tokena tog uređaja; ostali uređaji ostaju prijavljeni.
4. `POST /auth/logout` `{refresh_token?, all_devices?}` → 204. Bez tela odjavljuje uređaj za koji je vezan trenutni access token; `all_devices: true` odjavljuje sve.
5. `POST /auth/password-reset` `{email}` → uvek 204; link vodi na `/nova-lozinka/{token}/` na sajtu.
6. `GET /me`, `PATCH /me` (`name`, `phone`, `location_id`, `avatar_media_id`, `notifications`), `DELETE /me` `{password}` → 204, `GET /me/limits`, `GET /me/overview` (counts, tasks with type/count/text/target, last_30_days, limits, plus_seller_until). `/me` also has `plus_seller_until`.

API ne prihvata sesiju sajta (`config/sanctum.php`: `guard` prazan), samo Bearer token. Promena ili reset lozinke i brisanje naloga opozivaju sve tokene.

### Kodovi grešaka

| HTTP | `code` | Kada |
| --- | --- | --- |
| 401 | `unauthorized` | nema tokena |
| 401 | `token_expired` | token je istekao, opozvan ili ne postoji: osvežiti |
| 401 | `invalid_refresh_token` | refresh token ne važi: ponovo prijava |
| 401 | `invalid_credentials` | pogrešan email ili lozinka (i pogrešna lozinka za `DELETE /me`) |
| 401 | `account_blocked` | nalog je blokiran |
| 403 | `forbidden` | nema prava |
| 404 | `not_found` | ne postoji |
| 409 | `email_taken` | registracija sa postojećim emailom |
| 422 | `validation_failed` | `fields` ima po jednu poruku za svako polje |
| 429 | `too_many_requests` | previše pokušaja (zaglavlje `Retry-After`) |

`Me.role`: `private`, `firm` ili `admin` (administrator i moderator). `avatar` je `null` kad nema slike; kad ima, sva tri URL-a su ista slika 400 × 400 (WebP).

## Baza

- `personal_access_tokens` (Sanctum) sa kolonom `refresh_token_id`.
- `users.location_id`, `users.notification_settings` (JSON), `refresh_tokens.replaced_by_id`.

## Testovi

`tests/Unit/PhoneTest.php` (isti slučajevi kao `telefon-test.php`) i `tests/Feature/Nalozi/` (strane, pravila, WordPress heševi sa poznatim primerima, API sa svim greškama). U testovima argon2id radi sa malo memorije (`ARGON_MEMORY`, `ARGON_TIME` u `phpunit.xml`).
