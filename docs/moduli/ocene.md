# Ocene prodavaca

Kupac ocenjuje prodavca od 1 do 5 zvezdica, sa komentarom (do 500 znakova), iz razgovora o oglasu. Pravila su u `App\Services\Ratings\SellerRatings`; sajt, API i admin idu samo preko tog servisa.

## Ko sme da oceni

- samo kupac iz razgovora (`conversations.buyer_id`), nikad prodavac ni treća osoba (404);
- tek kad su obe strane napisale bar jednu poruku (prodavac je odgovorio);
- potvrđena e-pošta, nalog nije blokiran, prodavac i dalje ima nalog;
- jedna ocena po paru kupac–prodavac (`unique(rater_id, seller_id)`); menja se `EDIT_DAYS` (30) dana od prve ocene.

## Prikaz

- profil (`/korisnik/{slug}/`): prosek, raspodela 5..1 i 3 najnovije; sve na `/korisnik/{slug}/ocene/` (noindex, 20 po strani);
- kartica prodavca na oglasu: „★ 4,5 (2 ocene)” kad ima ocena;
- kupac se prikazuje kao „Marko P.”; komentari se uvek escapuju;
- `users.rating_avg` i `users.rating_count` (samo vidljive ocene) osvežava `SellerRatings::refresh()`; `summary()` je u kešu 10 min i briše se pri svakoj promeni.

## Odgovor, prijava, admin

- prodavac jednom javno odgovara (može da izmeni ili obriše) i može da prijavi ocenu sa razlogom;
- admin: Moderacija › Ocene prodavaca (tab „Prijavljene”, filter 1–2 zvezdice); „Sakrij” izbacuje ocenu iz proseka, „Prijava neosnovana” / „Prikaži ponovo” vraća;
- sve ide u `audit_logs` (`rating.created|updated|replied|reported|hidden|shown`);
- prva ocena šalje prodavcu mejl i push (`NewRatingNotification`, podešavanje „Poruke”, poštuje NOTIFICATIONS_HOLD);
- brisanje naloga briše date i dobijene ocene i preračunava prosek ocenjenih prodavaca.

## Rute

| Sajt | |
|---|---|
| `GET /korisnik/{slug}/ocene/` | javna lista |
| `POST /moj-nalog/poruke/{id}/ocena` | score 1–5, comment |
| `POST /ocene/{id}/odgovor` | reply (prodavac) |
| `POST /ocene/{id}/prijava` | reason (prodavac) |

| API `/api/v1` | |
|---|---|
| `GET /users/{id}/ratings?page=` | `{summary: {average, count, distribution}, items: [...], next_page}` |
| `GET /conversations/{id}/rating` | `{can_rate, reason, rating}` |
| `PUT /conversations/{id}/rating` | `{score, comment}` → 201 nova, 200 izmena; 422 `not_allowed` sa razlogom |
| `POST /ratings/{id}/reply` | `{text}` (prodavac, inače 404) |
| `POST /ratings/{id}/report` | `{reason}` → 202 |

Stavka: `id, score, comment, rater_name, listing {id, title}|null, reply, replied_at, created_at, can_reply, can_report`. Prodavac u kartici oglasa ima `rating: {average, count}`.

Pravila za korisnike: Pravila oglašavanja („Ocene prodavaca”), Uslovi korišćenja tačka 7, Politika privatnosti („Šta drugi korisnici vide”).
