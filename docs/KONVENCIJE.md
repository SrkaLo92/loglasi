# Konvencije

## Opšte

- Sav tekst koji korisnik vidi je na srpskom, latinica. Kod, imena klasa i kolona na engleskom.
- URL-ovi sajta se završavaju kosom crtom (`/oglas/{slug}/`, `/oglasi/{kategorija}/`), kao na sadašnjem sajtu. Za linkove koristiti `url_slash()`.
- Boje i tokeni su u `public/css/osnova.css` (`--lg-*`). Dugmad: zelena `#2B7A64`, narandžasta `#B8501A`; brend `#379B7F` / `#EF7A38` samo za akcente.
- Bez CSS/JS okvira i bez build koraka: običan CSS po modulu u `public/css/<modul>.css`, JS samo kad je neophodan, učitava se sa `asset_v()`.
- Stranice moraju da rade bez JavaScript-a; JS samo poboljšava.

## Moduli

- Svaki modul ima svoje: `routes/web/<modul>.php`, `routes/api/<modul>.php`, `app/Http/Controllers/<Modul>/`, `resources/views/<modul>/`, `public/css/<modul>.css`, testove u `tests/Feature/<Modul>/`.
- Zajedničke tabele su definisane u `database/migrations/2026_10_24_*`. Postojeće migracije se ne menjaju; izmena šeme = nova migracija sa imenom modula.
- Zajednički modeli (`app/Models`) mogu da dobiju nove metode i relacije; postojeće se ne menjaju bez dogovora.

## API

- Ugovor je `api/openapi.yaml` iz repozitorijuma mobilne aplikacije; oblici odgovora i greške tačno po njemu.
- Greška: `{"code": "...", "message": "...", "fields": {...}}`, poruke na srpskom.

## Bezbednost

- Svaka akcija koja menja podatke proverava dozvolu (Policy). Admin akcije se upisuju u `audit_logs` (`AuditLog::record`).
- Upit uvek preko Eloquent-a ili Query Builder-a sa parametrima, nikad spajanjem stringova.
- Ograničenje broja zahteva (rate limit) na prijavi, registraciji, slanju poruka, otkrivanju broja i prijavama.

## Testovi

- Svaka ruta i svako poslovno pravilo ima test. `php artisan test` i `vendor/bin/pint --test` moraju da prođu pre spajanja.
