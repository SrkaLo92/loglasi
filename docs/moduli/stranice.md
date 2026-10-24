# Modul „Stranice”: izgled sajta i sadržajne strane

Zaglavlje, podnožje i donja navigacija za telefon (po odobrenoj maketi početne), sadržajne strane (Cenovnik, Za firme, Kontakt, O nama, Česta pitanja, Uslovi korišćenja, Politika privatnosti), strane grešaka i zajednički srpski rečnik.

## Adrese

| Strana | URL | Ruta |
| --- | --- | --- |
| Cenovnik | `/cenovnik/` | `stranice.cenovnik` |
| Za firme | `/za-firme/` | `stranice.za-firme` |
| Kontakt (forma: POST na istu adresu) | `/kontakt/` | `stranice.kontakt`, `stranice.kontakt.posalji` |
| O nama | `/o-nama/` | `stranice.o-nama` |
| Česta pitanja | `/faq/` | `stranice.faq` |
| Uslovi korišćenja | `/uslovi-koriscenja/` | `stranice.uslovi` |
| Politika privatnosti | `/politika-privatnosti/` | `stranice.privatnost` |

`/kontakt/`, `/cenovnik/`, `/za-firme/`, `/o-nama/` i `/faq/` su potvrđeni u dokumentima (09-deploy-seo.md, llms.txt lista, NAPOMENE-stranice.md). **Uslovi i privatnost: slugovi nisu nađeni u dokumentima**, pre prelaska proveriti u GSC izvozu i po potrebi promeniti putanju u `routes/web/stranice.php` i `config/stranice.php` (ili dodati 301).

Ostale putanje koje zaglavlje i navigacija koriste (`/oglasi/`, `/postavi-oglas/`, `/moj-nalog/`, `/moj-nalog/poruke/`, `/login-registracija/`, `/brisanje-podataka/`) su na jednom mestu: `config/stranice.php` → `linkovi`.

## Fajlovi

| Fajl | Šta radi |
| --- | --- |
| `resources/views/layouts/app.blade.php` | Telo strane: preskok na sadržaj, zaglavlje, `<main>`, podnožje, donja navigacija. `partials/seo-head` pripada modulu SEO. |
| `resources/views/partials/header.blade.php` | Logo, pretraga (GET `/oglasi/`, polja `keyword`, `kategorija`, `mesto`), Prijava / Moj nalog, „Postavite oglas”. Na telefonu: logo, „Loznica i okolina”, pretraga i dugme za filtere. |
| `resources/views/partials/footer.blade.php` | Logo, rečenica „loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.”, linkovi na sadržajne strane. |
| `resources/views/partials/bottom-nav.blade.php` | Donja navigacija ispod 768 px (Početna, Pretraga, Postavi oglas, Poruke, Nalog), kao `inc/home/sadrzaj.php`. Broj nepročitanih poruka: promenljiva `$unreadMessages` (modul poruka je deli sa `View::share`). Sakriva se sa `$hideMobileNav = true`. |
| `public/css/osnova.css` | Tokeni boja, font, zaglavlje, podnožje, donja navigacija, dugmad. Učitava se na svakoj strani. |
| `public/css/stranice.css` | Sadržajne strane (klase `lgx-`, preneto iz `assets/stranice/stranice.css`), forma, tekst, greške. Učitava se samo na ovim stranama. |
| `public/js/stranice.js` | Samo dugme „Kopiraj” za broj računa; učitava se kad je račun unet. |
| `public/fonts/` | Manrope (OFL), latinica i proširena latinica, varijabilni 200–800. |
| `app/Support/Stranice/` | `PageText` (tekstovi), `Cenovnik` (cene, brojač firmi, podaci za uplatu), `Navigacija` (kategorije i mesta za pretragu, aktivna stavka), `Telefon` (E.164 i prikaz), `Ikonice` (SVG). |
| `app/Http/Controllers/Stranice/` | `CenovnikController`, `ZaFirmeController`, `KontaktController`, `TekstController`. |
| `app/Mail/KontaktPoruka.php`, `resources/views/stranice/mail/kontakt.blade.php` | Poruka sa forme, ide u red (queue). |
| `resources/stranice/tekstovi.php` | Podrazumevani tekstovi svih strana. |
| `resources/views/errors/` | 401, 403, 404, 410, 419, 429, 500, 503 i rezervne 4xx/5xx. |
| `lang/sr/` | `validation.php`, `auth.php`, `passwords.php`, `pagination.php`, `ui.php` (zajednički nazivi dugmadi i oznaka). |

## Tekstovi iz admina

Svaki tekst se čita iz podešavanja pod ključem `page_text.<ključ>` (npr. `page_text.o_nama.tekst`, `page_text.faq.pitanja`). Dok admin ništa ne upiše, prikazuje se podrazumevani tekst iz `resources/stranice/tekstovi.php`. Admin modul samo treba da ponudi polja za te ključeve i da ih upiše kroz `Settings::set()`.

- Kratki tekstovi su obični stringovi, dugi (O nama, Uslovi, Privatnost) su Markdown: `## naslov`, `- stavka`, `**masno**`, `[tekst](/putanja/)`. HTML se ne prihvata (escapuje se), nebezbedni linkovi se izbacuju.
- Liste (pitanja, koraci, prednosti) su nizovi: pitanje je `{pitanje, odgovor}`, korak `{naslov, tekst}`, prednost `{ikonica, naslov, tekst}`. Pitanje na Cenovniku ima i `uslov` (šifra proizvoda): ako proizvod nije aktivan, pitanje se ne prikazuje.
- Zamene u tekstu se popunjavaju iz baze, pa cena nikad nije upisana ručno: `{cena_podizanje}`, `{cena_istaknuto_7}`, `{cena_istaknuto_30}`, `{cena_biznis}`, `{besplatni_oglasi}` („do 10 novih oglasa”), `{trajanje_oglasa}` („mesec dana”), `{besplatne_firme}`, `{ponuda_firme}`, `{email}`.
- Prikaz u Blade-u: `PageText::get()` (tekst), `PageText::html()` (Markdown), `PageText::inline()` (jedan red sa linkom), `PageText::list()`.

Tekstovi strana 500 i 503 su u samom šablonu, jer se prikazuju i kad baza ne radi.

## Podaci

- **Cene:** tabela `products` (aktivni, po `sort`). Naziv i cena dolaze iz baze; opis iz `products.description`, a ako je prazan, podrazumevani opis za šifru (`cenovnik.opis.<šifra>`). Trajanje: bez dana „jednokratno”, 365 „godišnje”, inače „N dana”.
- **Brojač za firme:** `founding_firms_total` (podešavanje, 30) minus broj firmi sa `founding_free = true`; keš 5 minuta (`Cenovnik::forgetFounding()` ga briše). Pravilni oblici: 1 besplatno mesto, 23 besplatna mesta, 29 besplatnih mesta; 0 → „Sva besplatna mesta su popunjena” i samo cena.
- **Besplatni oglasi i trajanje:** `private_monthly_limit`, `listing_duration_days`.
- **Uplata (prazno dok admin ne unese, račun se nigde ne izmišlja):** `payment_recipient`, `payment_account`, `payment_model`, `payment_reference`, `payment_purpose`. Dok primalac i račun nisu uneti, piše „Podatke za uplatu šaljemo na zahtev…”. Formatiranje računa (3-13-2) radi admin pri upisu.
- **Kontakt:** `contact_email` (prima poruke sa forme), `contact_phone` (bilo koji oblik, prikaz „+381 65 123 4567”, link `tel:+381…`), `contact_hours` (red po red).
- **Pretraga u zaglavlju:** glavne aktivne kategorije i mesta, keš 10 minuta (`Navigacija::forget()`); ako baza ne radi, izbor se ne prikazuje.

## Kontakt forma

Sopstvena forma umesto Contact Form 7, sa poljima i porukama stare forme „Pomoć”: Ime (do 80), E-mail ili telefon (do 120; telefon se čuva kao E.164), Tema (prazna opcija „— Izaberite —”), Poruka (do 2000), dugme „Pošalji poruku”. Svi tekstovi i poruke su u `kontakt.*` u `tekstovi.php`. Zaštita:

1. CSRF token.
2. Polje-zamka `sajt` (van ekrana, van tabulatora): ako je popunjeno, botu se pokaže uspeh, a ništa se ne šalje.
3. Vremenska zamka `_t` (šifrovano vreme otvaranja forme): poslato za manje od 3 s → tiho odbačeno; nema tokena, lažan ili stariji od 12 h → „Forma je istekla…”.
4. Limit: 3 poruke na 10 minuta po IP adresi (poruka na srpskom) + opšti `throttle:20,1` na ruti.

Poruka ide kao `KontaktPoruka` (ShouldQueue) na `contact_email`, „Odgovori na” je adresa pošiljaoca kad je ostavio e-mail (za telefon nema). Vrednosti su u `config/stranice.php` → `kontakt`. Link „Prijavite firmu” sa strane Za firme vodi na `/kontakt/?tema=firma#forma` i bira temu „Za firme”.

## Izgled i pristupačnost

- Maketa: logo „loglasi**.rs**”, pretraga sa zelenim okvirom (#379B7F), dugme „Postavite oglas” narandžasto (#B8501A), tamno podnožje (#1D2B26), donja navigacija sa izdignutim narandžastim dugmetom. Odstupanje od makete: u podnožju su dodati linkovi Cenovnik i Za firme, i red sa ©.
- Font Manrope sa sajta (preload, `font-display: swap`) i rezervni font sa podešenim merama (`size-adjust`, `ascent-override`), pa zamena fonta ne pomera stranu. Bez spoljnih zahteva.
- Telefon (< 768 px): razmak 16 px, nalog i „Postavi oglas” su u donjoj navigaciji, telo dobija donji razmak za traku. Između 768 i 1100 px izbor mesta u pretrazi se skriva.
- Sve ikonice su SVG u HTML-u (`aria-hidden`), linkovi i dugmad najmanje 44 px, vidljiv fokus (narandžasti okvir), „Pređi na sadržaj”, labele za sva polja, greške uz polje (`aria-invalid`, `aria-describedby`), poruke `role="status"` / `role="alert"`. Strane rade bez JavaScript-a (FAQ je `<details>`).

## Testovi

`tests/Feature/Stranice/`: svaka strana 200 sa očekivanim naslovom i jednim H1, bez engleskih reči u tekstu i atributima, bez „lorem”; kanonski URL; cene iz baze (izmena, neaktivan proizvod, opis iz baze); brojač firmi (oblici, popunjeno, ukupno iz podešavanja); podaci za uplatu; kontakt forma (validacija, zamka, vremenska zamka, istek, limit, poruka u redu, tekst poruke); strane grešaka; zaglavlje, navigacija i fontovi; telefon.

```bash
DB_DATABASE=lb_stranice_test php artisan test tests/Feature/Stranice
```

## Otvorena pitanja

- Slugovi Uslova i Politike privatnosti na sadašnjem sajtu.
- Nazivi parametara pretrage (`keyword`, `kategorija`, `mesto`) treba da se poklope sa modulom oglasa; `keyword` i `kategorija` su kao na sadašnjem sajtu.
- Tekstovi O nama, Uslovi i Politika privatnosti su napisani nanovo (u referentnom kodu ih nema); vlasnik treba da ih pročita, a uslove i politiku privatnosti i pravnik (naziv rukovaoca podacima, povraćaj uplate, analitika i kolačići ako se uvedu).
- FAQPage schema za `/faq/` je posao modula SEO; pitanja su u `PageText::list('faq.pitanja')`.
