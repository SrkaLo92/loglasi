# Uvoz iz WordPress-a

Komanda `php artisan import:wordpress` prenosi sadržaj sadašnjeg sajta (WordPress + Listivo + loglasi-core) u novu bazu. Može da se pokreće koliko god puta: zapis se traži po izvornom ID-ju (`wp_id`, `wp_term_id`, `wp_key`) i menja, ne duplira se.

Šta ostaje isto, da URL-ovi ne bi promenili adresu:

- **oglasi** imaju isti ID kao u WordPress-u (`listings.id = wp_posts.ID`) i isti slug (`/oglas/{slug}/`);
- **korisnici** dobijaju isti ID kad je slobodan i slug = `user_nicename` (`/korisnik/{slug}/`);
- **kategorije** i **mesta** zadržavaju slug (`/oglasi/{slug}/`).

## Šta se prenosi

| Deo (`--only`) | Odakle | Napomena |
| --- | --- | --- |
| `settings` | opcije `loglasi_kurs_eur`, `loglasi_cenovnik`, `loglasi_pocetna_besplatni_oglasi`, `loglasi_pocetna_biznis_besplatno` | kurs evra, mesečni limit, broj besplatnih firmi, cene i aktivnost proizvoda (podizanje, isticanje 7/30, Biznis) |
| `users` | `wp_users` + `wp_usermeta` | heš lozinke se prenosi neizmenjen (`$P$…` i `$wp$2y$…`, nalog ga menja pri prvoj prijavi); `administrator` → uloga admin; `listivo_account_type = business` → nalog firme; telefon `listivo_phone` (+ `listivo_phone_country_code`) u E.164; `loglasi_telefon_proveren`; profilna slika `listivo_user_image` |
| `categories` | taksonomija `listivo_14` | stablo, slug, opis, redosled (term meta `order`), ikonica ako je ključ podešen |
| `locations` | taksonomija `listivo_14144` | ravna lista (naselja su posebna mesta) |
| `firms` | user meta `loglasi_firma_*` | opis, adresa, mesto, radno vreme, sajt, mreže, logo, naslovna, „Proverena firma”; prvih 30 firmi po datumu registracije dobija oznaku `founding_free` |
| `fields` | taksonomije `listivo_<ID>` i meta `listivo_<ID>` na oglasima, plus privremena `loglasi_stanje`, `loglasi_vrsta_oglasa`, `loglasi_moguca_zamena`, `loglasi_preuzimanje` | naziv polja je naslov Listivo objave sa istim ID-jem; tip se zaključuje iz podataka (izbor, više izbora, broj, tekst) |
| `listings` | `wp_posts` tipa `listivo_listing` | status, naslov, opis (čist tekst), datumi, cena `listivo_130_listivo_<valuta>` (435 = EUR, ostalo prema `cena.php`), istaknut `listivo_featured`, pregledi `loglasi_views`, kategorija (najdublja), mesto (naselje pre opštine), vrednosti polja, slike (`_thumbnail_id` + galerija `listivo_145`) |
| `favorites` | user meta `listivo_favorites` | spisak ID-jeva oglasa |
| `messages` | tabela `wp_listivo_messages` | razgovor = oglas + kupac; stare poruke se vode kao pročitane |
| `gone` | spam nalozi #80, #106, #109 i oglasi u otpadu | upisuju se u `gone_urls` (410): `/korisnik/{slug}/`, `/author/{slug}/`, `/oglas/{slug}/` |
| `orders` | `shop_order` / `wc_orders` | **ne prenose se**, samo se prebrojavaju u izveštaju |

Statusi oglasa: `publish` → aktivan, `pending`/`future` → čeka odobrenje, `draft`/`private` → nacrt, sve sa „expire” → istekao, `trash` → ne prenosi se (URL ide u 410; ako je oglas ranije uvezen, ovde se briše), `auto-draft` se preskače.

Pošto WordPress ne čuva datum isteka, objavljen oglas pri prvom uvozu dobija 30 dana od dana uvoza, a istaknut oglas ostaje istaknut 30 dana od uvoza (`config/wordpress_import.php`: `listing_days`, `featured_days`). Ponovljen uvoz ove datume ne pomera.

Spam nalozi i sav njihov sadržaj (oglasi, slike, poruke, omiljeni) se ne prenose.

## Pokretanje na Mac-u (Local)

1. U aplikaciji Local pokrenuti sajt loglasirs.local i otvoriti tab **Database**. Tu piše: Database name (obično `local`), Username/Password (`root`/`root`), **Port** i **Socket**.
2. U `.env` nove aplikacije upisati:

   ```dotenv
   WP_DB_HOST=127.0.0.1
   WP_DB_PORT=10005            # port iz taba Database
   WP_DB_SOCKET="/Users/<ime>/Library/Application Support/Local/run/<id>/mysql/mysqld.sock"
   WP_DB_DATABASE=local
   WP_DB_USERNAME=root
   WP_DB_PASSWORD=root
   WP_DB_PREFIX=wp_
   ```

   Dovoljno je jedno od ta dva: port ili socket. Ako je zadat socket, port se ne koristi. Ako veza ne radi preko porta, probati socket (Local ga uvek ima).
3. Nova baza mora biti prazna, bez probnog admin naloga, da bi korisnici dobili iste ID-jeve:

   ```bash
   php artisan migrate:fresh --force
   php artisan db:seed --class=ProductSeeder
   php artisan db:seed --class=PayeeSeeder   # podaci primaoca uplate, samo ako su prazni
   ```

   (`php artisan migrate --seed` u lokalnom okruženju pravi i nalog `admin@loglasi.rs` sa ID-jem 1, pa bi WordPress nalog #1 dobio drugi ID.)
4. Prvo proba, bez upisa:

   ```bash
   php artisan import:wordpress --dry-run
   ```

5. Pravi uvoz sa slikama (putanja do `wp-content/uploads` Local sajta):

   ```bash
   php artisan import:wordpress --uploads="$HOME/Local Sites/loglasirs/app/public/wp-content/uploads"
   php artisan storage:link
   ```

   Originali se kopiraju na disk `public` u `oglasi/{id oglasa}/`, `avatari/{id}/`, `firme/{id}/`. Veličine slika (`variants`) pravi modul slika posle uvoza.

Delovi mogu i posebno, npr. `--only=categories,locations,fields`. Delovi koriste ono što je ranije uvezeno (oglasi traže već uvezene korisnike i kategorije).

### Poslednji uvoz pred prelazak

Posle punog uvoza, na dan prelaska prenose se samo izmene od preseka:

```bash
php artisan import:wordpress --since="2026-11-01 08:00" --uploads=...
```

Uzimaju se oglasi napravljeni ili izmenjeni posle tog trenutka, novi korisnici i autori tih oglasa, i nove poruke. Kategorije, mesta, polja, podešavanja, omiljeni i 410 lista se uvek prolaze cele. Izveštaj navodi i oglase koji postoje u novoj bazi, a u WordPress-u ih više nema.

## Izveštaj

Na kraju se ispisuje tabela (po delu: u WP, uvezeno, preskočeno, greške), a detalji idu u `storage/app/uvoz/izvestaj-{datum}.txt`: svaka preskočena stavka i razlog, svaka greška, pronađena polja (`listivo_1910 → marka_automobila`), kurs i cene.

## Šta proveriti posle uvoza

1. **Brojevi:** broj oglasa po statusu i korisnika u izveštaju uporediti sa WordPress-om (`SELECT post_status, COUNT(*) FROM wp_posts WHERE post_type='listivo_listing' GROUP BY 1`). Razlika mora da bude objašnjena u izveštaju (spam, otpad, greške).
2. **Greške:** svaku liniju „GREŠKA” iz izveštaja rešiti (npr. oglas bez kategorije, autor koji ne postoji, zauzet slug) i ponoviti uvoz.
3. **Cene:** 5 oglasa u evrima i 5 u dinarima (`12.500 din`). Ako je dinar pogrešno prepoznat, u `config/wordpress_import.php` upisati `currency_term_rsd` (ID iz ključa `listivo_130_listivo_<ID>`).
4. **Polja:** spisak iz izveštaja. Nazive, ključeve i jedinice (km, m²) ispraviti u `config/wordpress_import.php` → `fields` (po `wp_key`) i ponoviti `--only=fields,listings`.
5. **Telefoni:** linije „neispravan telefon” iz izveštaja proveriti ručno.
6. **Lozinke:** prijaviti se jednim starim nalogom starom lozinkom.
7. **Slike:** greške „nema fajla” znače da fajl nije u `uploads`; proveriti putanju u `--uploads`.
8. **Firme:** svaka firma ima profil, logo i radno vreme; „Delatnost” nema svoje polje u novoj šemi (vidi izveštaj).
9. **410:** nijedan URL sa 410 liste ne sme da postoji u novoj bazi; uvoz to sam proverava i javlja kao grešku.
10. **Poruke:** ako izveštaj kaže da tabela `wp_listivo_messages` ne postoji, pogledati `SHOW TABLES LIKE '%listivo%'` i upisati pravo ime tabele i kolona u `config/wordpress_import.php` → `messages`.

## Podešavanja (`config/wordpress_import.php`)

Svi Listivo ključevi su na jednom mestu, isti kao u loglasi-core: tip oglasa, taksonomije, polje cene i ID termina valute, meta ključevi (istaknut, galerija, pregledi, istek, koordinate, telefon oglasa), user meta (tip naloga, telefon, pozivni broj, potvrđen telefon, slika, omiljeni, polja firme), term meta (redosled, ikonica), tabela poruka, spam nalozi i broj dana za istek i isticanje. Prazan ključ znači da tog podatka nema.
