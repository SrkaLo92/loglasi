<?php

/*
 * Podrazumevani tekstovi sadržajnih strana. Admin ih menja kroz podešavanja
 * (ključ „page_text.<ključ>”); dok izmene nema, prikazuje se tekst odavde.
 *
 * Zamene u vitičastim zagradama popunjavaju se iz baze (cene iz tabele products,
 * limiti iz podešavanja), da se cena nikad ne piše ručno u tekstu:
 *   {cena_podizanje} {cena_istaknuto_7} {cena_brza_prodaja} {cena_plus_prodavac} {cena_vizitka} {cena_paket_podizanja} {cena_prioritet} {cena_top}
 *   {cena_premium_xl} {cena_start} {cena_pro} {cena_max}
 *   {besplatni_oglasi} {besplatne_firme} {trajanje_oglasa} {email}
 *
 * Dugi tekstovi su Markdown (## naslov, - stavka, [tekst](/putanja/)); HTML se ne prihvata.
 */

return [

    /* Cenovnik ------------------------------------------------------------ */

    'pocetna.zasto.naslov' => 'Zašto izabrati nas',
    'pocetna.pitanja.naslov' => 'Najčešća pitanja',
    'pocetna.zasto' => [
        ['ikonica' => 'pin', 'naslov' => 'Lokalna povezanost', 'tekst' => 'Povezujemo ljude iz Loznice i okolnih mesta. Sve je blizu, pa stvar možete da pogledate uživo pre kupovine.'],
        ['ikonica' => 'click', 'naslov' => 'Jednostavnost korišćenja', 'tekst' => 'Sajt je pregledan i lak za korišćenje, i onima koji se ne snalaze najbolje sa tehnologijom. Oglas postavljate za par minuta.'],
        ['ikonica' => 'shield', 'naslov' => 'Sigurnost i poverenje', 'tekst' => 'Kupac i prodavac se dogovaraju direktno, bez posrednika i provizije. Sumnjiv oglas možete da nam prijavite jednim klikom.'],
        ['ikonica' => 'gift', 'naslov' => 'Besplatno oglašavanje', 'tekst' => 'Privatna lica imaju {besplatni_oglasi} besplatno, bez skrivenih troškova. Plaćate samo ako želite da istaknete oglas.'],
        ['ikonica' => 'support', 'naslov' => 'Podrška korisnicima', 'tekst' => 'Tu smo kad zapne: pomažemo oko postavljanja oglasa i rešavamo probleme. Pišite nam ili pozovite.'],
        ['ikonica' => 'search', 'naslov' => 'Brza i efikasna pretraga', 'tekst' => 'Kategorije i filteri po ceni i mestu brzo vas vode do onoga što tražite.'],
    ],

    'cenovnik.naslov' => 'Cenovnik',
    'cenovnik.uvod' => 'Postavljanje oglasa je besplatno. Plaćate samo ako želite da vaš oglas vidi više ljudi, ili ako predstavljate firmu.',
    'cenovnik.privatni.naslov' => 'Za privatne korisnike',
    'cenovnik.besplatno.naziv' => 'Običan oglas',
    'cenovnik.besplatno.stavke' => [
        'Oglas traje {trajanje_oglasa}',
        'Obnova je besplatna',
        'Bez provizije na prodaju',
    ],
    'cenovnik.mesta.naslov' => 'Treba vam više oglasa?',
    'cenovnik.mesta.uvod' => 'Dodatna mesta za oglase, važe 30 dana',
    'cenovnik.promocije.naslov' => 'Promocije oglasa',
    'cenovnik.promocije.uvod' => 'Da vaš oglas vidi više kupaca',
    'cenovnik.promocije.napomena' => 'Za sada se plaća uplatom na račun, a usluga se uključuje čim uplata stigne.',
    'cenovnik.firme.naslov' => 'Za firme',
    'cenovnik.firme.oznaka' => 'Prvih {besplatne_firme} firmi besplatno',
    'cenovnik.firme.ponuda' => '12 meseci besplatno za prvih {besplatne_firme} firmi',
    'cenovnik.firme.stavke' => [
        'Neograničeno standardnih oglasa',
        'Profil firme i oznaka „Proverena firma”',
        '5 podizanja i 1 isticanje svakog meseca',
        'Početni uvoz oglasa i podrška',
    ],
    'cenovnik.firme.napomena' => 'Posle 12 meseci nema automatske naplate. Sami birate da li nastavljate i sa kojim paketom.',
    'cenovnik.paketi.naslov' => 'Paketi posle prve godine',
    'cenovnik.paketi.uvod' => 'Za ostale firme, i za prvih {besplatne_firme} kad istekne besplatna godina. Godišnje: platite 10 meseci, koristite 12.',
    'cenovnik.paketi.istaknut' => 'business_pro',
    'cenovnik.dodatno.naslov' => 'Dodatno oglašavanje za firme',
    'cenovnik.dodatno' => [
        ['naziv' => 'Partner nedelje', 'opis' => 'Mesto na početnoj i objave na društvenim mrežama', 'cena' => '3.990 RSD / 7 dana'],
        ['naziv' => 'Predstavljanje na mrežama', 'opis' => 'Reel ili video konkretnog proizvoda', 'cena' => '5.000–10.000 RSD'],
        ['naziv' => 'Sponzor kategorije', 'opis' => 'Vaša firma uz celu kategoriju', 'cena' => '7.500–15.000 RSD / mes.'],
        ['naziv' => 'Promo blok na početnoj', 'opis' => 'Istaknut blok vaše firme', 'cena' => '5.000–10.000 RSD / mes.'],
    ],
    'cenovnik.opis.bump' => 'Oglas se vraća na vrh liste kao da je upravo postavljen. Korisno kad oglas potone ispod novijih.',
    'cenovnik.opis.featured_7' => 'Oglas je izdvojen na početnoj strani i u rezultatima pretrage, sa oznakom „Istaknuto”.',
    'cenovnik.plus.naslov' => 'Plus prodavac',
    'cenovnik.plus.uvod' => 'Za one koji prodaju više polovnih i svojih stvari. Ne važi za usluge, poslove i nekretnine od agencije: za to su paketi za firme.',
    'cenovnik.opis.biznis_12m' => 'Profil firme sa logoom i naslovnom slikom, oznaka „Proverena firma”, radno vreme, mapa i statistika.',
    'cenovnik.uplata.naslov' => 'Kako da platim',
    'cenovnik.uplata.uvod' => 'Za sada se plaća samo uplatom na račun.',
    'cenovnik.uplata.koraci' => [
        ['naslov' => 'Izaberite uslugu', 'tekst' => 'Pogledajte cene iznad i zapišite naziv usluge i naslov ili broj (ID) oglasa na koji se odnosi.'],
        ['naslov' => 'Uplatite na račun', 'tekst' => 'Uplatu možete da obavite u banci, pošti ili kroz mobilno bankarstvo. Plaćanje karticom za sada nije moguće.'],
        ['naslov' => 'Pošaljite potvrdu', 'tekst' => 'Pošaljite fotografiju ili PDF potvrde na {email} ili preko [kontakt forme](/kontakt/). Napišite koju uslugu ste platili i za koji oglas.'],
        ['naslov' => 'Uključujemo uslugu', 'tekst' => 'Kad uplata stigne, uslugu uključujemo ručno i javljamo vam se. Trajanje istaknutog oglasa računa se od dana uključenja.'],
    ],
    'cenovnik.uplata.nema' => 'Podatke za uplatu šaljemo na zahtev. [Pišite nam](/kontakt/) i navedite koju uslugu želite.',
    'cenovnik.cta.naslov' => 'Imate nešto za prodaju?',
    'cenovnik.cta.tekst' => 'Postavljanje traje par minuta, a prvi oglasi su besplatni.',
    'cenovnik.pitanja' => [
        ['uslov' => null, 'pitanje' => 'Da li je postavljanje oglasa besplatno?', 'odgovor' => 'Da. Privatni korisnici besplatno imaju {besplatni_oglasi}. Oglas traje {trajanje_oglasa}, a obnova je besplatna.'],
        ['uslov' => 'bump', 'pitanje' => 'Šta je podizanje oglasa?', 'odgovor' => 'Za {cena_podizanje} vaš oglas se vraća na vrh liste, kao da je upravo postavljen. Sadržaj oglasa i istorija ostaju isti.'],
        ['uslov' => 'featured_7', 'pitanje' => 'Šta dobijam kad istaknem oglas?', 'odgovor' => 'Istaknut oglas se prikazuje u izdvojenim oglasima na početnoj strani i u rezultatima pretrage, sa oznakom „Istaknuto”. Isticanje na 7 dana košta {cena_istaknuto_7}.'],
        ['uslov' => null, 'pitanje' => 'Kada se usluga uključuje posle uplate?', 'odgovor' => 'Čim vidimo uplatu na računu, obično istog ili sledećeg radnog dana. Ako pošaljete potvrdu o uplati, lakše ćemo je povezati sa vašim oglasom.'],
        ['uslov' => null, 'pitanje' => 'Da li mogu da platim karticom?', 'odgovor' => 'Za sada ne. Plaća se uplatom na račun, u banci, pošti ili kroz mobilno bankarstvo.'],
        ['pitanje' => 'Koliko košta oglašavanje za firme?', 'odgovor' => 'Prvih {besplatne_firme} firmi koristi loglasi.rs besplatno 12 meseci. Posle toga, kao i za ostale firme, birate jedan od paketa sa ove strane, uz mesečnu ili godišnju uplatu. Detalji su na strani [Za firme](/za-firme/).'],
    ],

    /* Za firme ------------------------------------------------------------ */

    'za_firme.oznaka' => 'Za firme',
    'za_firme.naslov' => 'Predstavite svoju firmu kupcima iz Loznice',
    'za_firme.uvod' => 'Profil firme sa logoom, radnim vremenom, mapom i svim vašim oglasima na jednom mestu. Kupci vas pozivaju jednim dodirom, a vi vidite koliko ljudi vas je pronašlo.',
    'za_firme.prednosti.naslov' => 'Šta dobija firma uz profil firme',
    'za_firme.prednosti' => [
        ['ikonica' => 'image', 'naslov' => 'Logo i naslovna slika', 'tekst' => 'Profil izgleda kao izlog vaše radnje, a ne kao nalog privatnog lica.'],
        ['ikonica' => 'badge', 'naslov' => 'Oznaka „Proverena firma”', 'tekst' => 'Podatke o firmi proveravamo pre uključenja, pa kupci znaju s kim posluju.'],
        ['ikonica' => 'clock', 'naslov' => 'Radno vreme i mapa', 'tekst' => 'Kupci odmah vide kada radite i kako da vas pronađu.'],
        ['ikonica' => 'grid', 'naslov' => 'Svi oglasi na jednom mestu', 'tekst' => 'Cela ponuda firme na jednoj adresi koju možete da podelite na mrežama.'],
        ['ikonica' => 'phone', 'naslov' => 'Pozovi, WhatsApp, Viber', 'tekst' => 'Dugmad za kontakt su pri vrhu profila i na svakom vašem oglasu.'],
        ['ikonica' => 'chart', 'naslov' => 'Statistika', 'tekst' => 'Pregledi profila i oglasa i broj kontakata, da znate šta donosi kupce.'],
    ],
    'za_firme.paketi.naslov' => 'Paketi posle besplatnog perioda',
    'za_firme.paketi.uvod' => 'Posle 12 meseci nema automatske naplate. Sami birate da li želite da nastavite sa nekim od paketa. Godišnje plaćate 10 meseci, a koristite 12.',
    'za_firme.poredjenje.naslov' => 'Privatni nalog ili profil firme',
    'za_firme.koraci.naslov' => 'Kako da se prijavite',
    'za_firme.koraci' => [
        ['naslov' => 'Napravite nalog', 'tekst' => 'Registrujte se na loglasi.rs ili se [prijavite](/login-registracija/) ako već imate nalog.'],
        ['naslov' => 'Pošaljite prijavu firme', 'tekst' => 'Naziv firme, PIB ili matični broj, adresu i telefon. Logo i naslovnu sliku možete poslati odmah ili kasnije.'],
        ['naslov' => 'Proveravamo podatke', 'tekst' => 'Proveravamo firmu i broj telefona, pa uključujemo profil firme i oznaku „Proverena firma”.'],
        ['naslov' => 'Popunite profil i dodajte oglase', 'tekst' => 'Dodajte radno vreme, lokaciju i opis, a vaši oglasi dobijaju oznaku „Firma”.'],
    ],
    'za_firme.cta.naslov' => 'Prijavite firmu dok ima besplatnih mesta',
    'za_firme.cta.naslov_popunjeno' => 'Prijavite svoju firmu',
    'za_firme.cta.tekst' => 'Popunite kratku formu i javićemo vam se da dogovorimo uključenje profila.',

    /* Oglas: saveti za bezbednu kupovinu ---------------------------------- */

    'oglas.saveti.naslov' => 'Saveti za bezbednu kupovinu',
    'oglas.saveti' => [
        'Pogledajte stvar uživo pre plaćanja i proverite da li odgovara opisu.',
        'Ne šaljite novac unapred i ne plaćajte preko linkova koje dobijete porukom.',
        'Nađite se na javnom i prometnom mestu, po danu, a za vozila i nekretnine proverite papire.',
        'Ne delite lozinke, kodove iz SMS-a ni podatke sa platne kartice.',
    ],

    /* Kontakt ------------------------------------------------------------- */

    'kontakt.naslov' => 'Kontakt',
    'kontakt.uvod' => 'Imate pitanje o oglasu, plaćanju ili paketima za firme? Pišite nam i odgovorićemo vam što pre, obično istog ili sledećeg radnog dana.',
    'kontakt.forma.naslov' => 'Pišite nam',
    'kontakt.poslato' => 'Hvala, poruka je poslata. Javićemo vam se uskoro.',
    'kontakt.teme' => [
        'Postavljanje oglasa',
        'Plaćanje i isticanje',
        'Nalog i prijava',
        'Za firme',
        'Prijava problema',
        'Nešto drugo',
    ],
    'kontakt.polje.ime' => 'Ime',
    'kontakt.polje.kontakt' => 'E-mail ili telefon',
    'kontakt.polje.kontakt_pomoc' => 'Odgovaramo na e-mail ili vas zovemo.',
    'kontakt.polje.tema' => 'Tema',
    'kontakt.polje.tema_prazno' => '— Izaberite —',
    'kontakt.polje.poruka' => 'Poruka',
    'kontakt.dugme' => 'Pošalji poruku',
    'kontakt.napomena' => 'Podatke iz forme koristimo samo da vam odgovorimo. Više u [Politici privatnosti](/politika-privatnosti/).',
    'kontakt.poruka.validacija' => 'Neka polja nisu dobro popunjena. Proverite označena polja i pošaljite ponovo.',
    'kontakt.poruka.spam' => 'Poruka nije poslata. Pokušajte ponovo malo kasnije.',
    'kontakt.poruka.istekla' => 'Forma je istekla. Proverite podatke i pošaljite poruku ponovo.',
    'kontakt.poruka.limit' => 'Poslali ste više poruka za kratko vreme. Pokušajte ponovo za {minuta}.',
    'kontakt.poruka.obavezno' => 'Ovo polje je obavezno.',
    'kontakt.poruka.predugacak' => 'Tekst je predugačak.',
    'kontakt.poruka.prekratak' => 'Tekst je prekratak.',
    'kontakt.poruka.kontakt' => 'Unesite ispravnu adresu e-pošte ili broj telefona, npr. 65 123 4567.',
    'kontakt.poruka.tema' => 'Izaberite temu sa liste.',

    /* O nama -------------------------------------------------------------- */

    'o_nama.naslov' => 'O nama',
    'o_nama.oznaka' => 'O nama',
    'o_nama.hero' => 'Oglasi iz Loznice, za ljude iz Loznice',
    'o_nama.uvod' => 'loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu. Prodajte, kupite ili ponudite uslugu nekome ko je blizu, bez posrednika i bez provizije.',
    'o_nama.prednosti.naslov' => 'Zašto lokalni oglasi',
    'o_nama.prednosti' => [
        ['ikonica' => 'pin', 'naslov' => 'Kupci i prodavci su blizu', 'tekst' => 'Loznica, Banja Koviljača, Lešnica, Tršić i okolna sela. Stvar pogledate uživo pre nego što je kupite.'],
        ['ikonica' => 'gift', 'naslov' => 'Besplatno i bez provizije', 'tekst' => 'Privatna lica imaju {besplatni_oglasi} besplatno. Od prodaje ne uzimamo ništa.'],
        ['ikonica' => 'phone', 'naslov' => 'Dogovor jednim dodirom', 'tekst' => 'Poziv, poruka preko sajta, WhatsApp ili Viber, kako vama odgovara.'],
        ['ikonica' => 'badge', 'naslov' => 'Pregledani oglasi', 'tekst' => 'Svaki oglas pregledamo pre objave, a prijavljene i sumnjive oglase uklanjamo.'],
    ],
    'o_nama.koraci.naslov' => 'Kako funkcioniše',
    'o_nama.koraci' => [
        ['naslov' => 'Napravite nalog', 'tekst' => 'Dovoljni su ime i e-pošta ili broj telefona. [Registracija](/login-registracija/) traje minut.'],
        ['naslov' => 'Postavite oglas', 'tekst' => 'Izaberite kategoriju, dodajte slike, naslov i cenu. Oglas traje {trajanje_oglasa}, a obnova je besplatna.'],
        ['naslov' => 'Dogovorite se uživo', 'tekst' => 'Kupci vas zovu ili vam pišu, a posao završite licem u lice, bez posrednika.'],
    ],
    'o_nama.zaradjujemo.naslov' => 'Kako zarađujemo',
    'o_nama.zaradjujemo.tekst' => 'Postavljanje oglasa je besplatno. Plaćaju se samo dodaci koje sami izaberete, kao što je isticanje oglasa, i paketi za firme.',
    'o_nama.firme.naslov' => 'Imate firmu ili radnju?',
    'o_nama.firme.tekst' => 'Profil firme sa logoom, radnim vremenom, mapom i svim oglasima na jednom mestu.',
    'o_nama.cta.naslov' => 'Pitanje, predlog ili problem?',
    'o_nama.cta.tekst' => 'Pišite nam, odgovaramo obično istog ili sledećeg radnog dana.',

    /* Česta pitanja ------------------------------------------------------- */

    'faq.naslov' => 'Česta pitanja',
    'faq.uvod' => 'Odgovori na pitanja koja nam najčešće postavljate. Ako ne nađete odgovor, [pišite nam](/kontakt/).',
    'faq.pitanja' => [
        ['pitanje' => 'Da li je postavljanje oglasa besplatno?', 'odgovor' => 'Da. Privatni korisnici besplatno imaju {besplatni_oglasi}. Plaćaju se samo promocije koje sami izaberete, kao što je isticanje oglasa.'],
        ['pitanje' => 'Kako da postavim oglas?', 'odgovor' => 'Prijavite se ili napravite nalog, pa kliknite na „Postavite oglas”. Izaberite kategoriju, upišite naslov, opis i cenu, dodajte fotografije i objavite oglas.'],
        ['pitanje' => 'Koliko dugo traje oglas?', 'odgovor' => 'Oglas traje {trajanje_oglasa}. Kad istekne, možete besplatno da ga obnovite iz svog naloga.'],
        ['pitanje' => 'Kako da kontaktiram prodavca?', 'odgovor' => 'Na strani oglasa su dugmad za poziv i poruku preko sajta, a ako je prodavac ostavio broj, i za WhatsApp i Viber.'],
        ['pitanje' => 'Kako da istaknem ili podignem oglas?', 'odgovor' => 'Isticanje na 7 dana košta {cena_istaknuto_7}, a „Brza prodaja” ({cena_brza_prodaja}) je isticanje na 7 dana i 3 podizanja. Plaća se uplatom na račun, a sve cene su na strani [Cenovnik](/cenovnik/).'],
        ['pitanje' => 'Šta dobija firma uz profil firme?', 'odgovor' => 'Profil firme sa logoom, radnim vremenom, mapom i oznakom „Firma” na svakom oglasu. {ponuda_firme} Više na strani [Za firme](/za-firme/).'],
        ['pitanje' => 'Kako da prijavim sumnjiv oglas?', 'odgovor' => 'Na strani oglasa kliknite na „Prijavi oglas” i ukratko napišite šta nije u redu. Svaku prijavu pregledamo.'],
        ['pitanje' => 'Kako da kupujem bezbedno?', 'odgovor' => 'Robu pogledajte uživo pre plaćanja, ne plaćajte unapred nepoznatim osobama i nikome ne šaljite kodove koje dobijete SMS porukom.'],
        ['pitanje' => 'Kako da obrišem nalog i svoje podatke?', 'odgovor' => 'Uputstvo je na strani [Brisanje ličnih podataka](/brisanje-licnih-podataka/). Možete i da nam pišete preko [kontakt forme](/kontakt/).'],
    ],

    /* Uslovi korišćenja --------------------------------------------------- */

    'uslovi.naslov' => 'Uslovi korišćenja',
    'uslovi.tekst' => <<<'MD'
Ovi uslovi važe za svakoga ko koristi sajt loglasi.rs i mobilnu aplikaciju loglasi.rs. Korišćenjem sajta prihvatate ove uslove.

## 1. Šta je loglasi.rs

loglasi.rs je sajt za oglase za Loznicu i okolinu. Sajt povezuje one koji prodaju i one koji kupuju, ali nije strana u njihovom dogovoru: ne prodaje robu, ne garantuje za nju i ne posreduje u plaćanju između kupca i prodavca.

## 2. Nalog

- Za postavljanje oglasa i slanje poruka potreban je nalog.
- Podaci u nalogu moraju biti tačni. Za nalog i lozinku odgovara vlasnik naloga.
- Firma dobija profil firme tek kada podatke proveri administrator sajta.

## 3. Oglasi

- Privatni korisnici besplatno imaju {besplatni_oglasi}, a firme prema svom paketu.
- Oglas traje {trajanje_oglasa}, a obnova je besplatna.
- Oglas mora da opisuje stvarnu stvar ili uslugu, sa tačnom cenom i fotografijama na koje imate pravo.
- Jedna stvar se oglašava jednim oglasom. Isti oglas postavljen više puta se briše.

## 4. Zabranjen sadržaj

Nije dozvoljeno oglašavanje:

- robe i usluga čija je prodaja zabranjena zakonom, oružja, droga i lekova na recept,
- falsifikovane i ukradene robe,
- sadržaja za odrasle, uvredljivog i diskriminatorskog sadržaja,
- lažnih oglasa, oglasa koji služe za prevaru ili prikupljanje podataka,
- reklama koje nisu oglas za konkretnu stvar ili uslugu.

## 5. Provera i uklanjanje

Oglase pregledamo i možemo da uklonimo oglas koji krši ove uslove, bez prethodne najave. Nalog koji više puta krši uslove može biti blokiran. Sumnjiv oglas možete prijaviti dugmetom „Prijavi oglas” na strani oglasa.

## 6. Plaćene usluge

- Postavljanje oglasa je besplatno. Plaćaju se dodatni oglasi, promocije oglasa (podizanje {cena_podizanje}, paket 10 podizanja {cena_paket_podizanja}, istaknut oglas {cena_istaknuto_7}, prioritetni oglas {cena_prioritet}, TOP oglas {cena_top}, Premium XL kartica {cena_premium_xl}) i paketi za firme (Business Start {cena_start}, Business Pro {cena_pro} i Business Max {cena_max} mesečno, ili godišnje po ceni za 10 meseci), po cenama sa strane [Cenovnik](/cenovnik/).
- Plaća se uplatom na račun, najlakše skeniranjem IPS QR koda u aplikaciji banke, uz poziv na broj sa predračuna. Ako uplata ne stigne u roku od 7 dana, porudžbina se otkazuje.
- Usluga počinje odmah posle potvrde uplate i traje koliko piše na Cenovniku. Paket kupljen tokom besplatne prve godine počinje kad ona istekne.
- Za isticanje, dodatne oglase i pakete, ako odustanete pre isteka, vraćamo srazmeran deo cene za dane koji nisu iskorišćeni; podizanje se izvrši odmah. Ako usluga ne može da se pruži našom greškom, vraćamo ceo iznos.
- Sve o naručivanju, plaćanju, računima, odustanku i reklamacijama piše na stranama [Uslovi kupovine i plaćanja](/uslovi-placanja/), [Odustanak od ugovora](/odustanak-od-ugovora/) i [Reklamacije i povraćaj novca](/reklamacije/).

## 7. Odgovornost

Za sadržaj oglasa odgovara onaj ko ga je postavio. loglasi.rs ne odgovara za tačnost oglasa, kvalitet robe ni za dogovor između kupca i prodavca. Preporučujemo da robu pogledate uživo i da ne plaćate unapred nepoznatim osobama.

## 8. Izmene uslova

Uslove možemo da izmenimo. Nova verzija važi od objave na ovoj strani.

## 9. Kontakt

Pitanja o ovim uslovima pošaljite preko [kontakt forme](/kontakt/) ili na {email}.
MD,

    /* Politika privatnosti ------------------------------------------------ */

    'brisanje.naslov' => 'Brisanje ličnih podataka',
    'brisanje.tekst' => <<<'MD'
Svoj nalog i lične podatke možete da obrišete u svakom trenutku, bez obrazloženja.

## Sami, iz naloga

1. Prijavite se na loglasi.rs.
2. Otvorite [Moj nalog](/moj-nalog/) i izaberite „Obriši nalog”.
3. Upišite lozinku, potvrdite i nalog se briše odmah.

## Na zahtev

Ako ne možete da se prijavite, pišite nam preko [kontakt forme](/kontakt/) ili na {email}, sa adrese e-pošte kojom ste se registrovali. Nalog brišemo najkasnije u roku od 30 dana i javljamo vam kad je gotovo.

## Šta se briše

- ime, adresa e-pošte, broj telefona, lozinka i slika profila
- svi vaši oglasi, sa slikama
- razgovori i poruke, omiljeni oglasi i sačuvane pretrage
- profil firme, ako ste ga imali
- prijave na aplikaciji i uređaji za obaveštenja

Podatke o plaćenim uslugama čuvamo bez ličnih podataka, onoliko koliko zakon traži za knjigovodstvo. Posle brisanja možete ponovo da se registrujete istom adresom e-pošte.

Više o tome kako postupamo sa podacima piše u [Politici privatnosti](/politika-privatnosti/).
MD,

    'privatnost.naslov' => 'Politika privatnosti',
    'privatnost.tekst' => <<<'MD'
Ova politika objašnjava koje podatke o ličnosti prikupljamo na sajtu loglasi.rs i u mobilnoj aplikaciji, zašto ih prikupljamo i koja prava imate. Podatke obrađujemo u skladu sa Zakonom o zaštiti podataka o ličnosti.

## Koje podatke prikupljamo

- **Podaci naloga:** ime, adresa e-pošte, broj telefona i lozinka (čuva se samo u šifrovanom obliku).
- **Podaci firme:** naziv, PIB ili matični broj, adresa, radno vreme, logo i naslovna slika, ako otvorite profil firme.
- **Oglasi i poruke:** sadržaj oglasa, fotografije i poruke koje razmenjujete sa drugim korisnicima.
- **Poruke preko kontakt forme:** ime, e-pošta, telefon (ako ga unesete) i sadržaj poruke.
- **Tehnički podaci:** IP adresa, vrsta uređaja i pregledača i vreme pristupa, radi bezbednosti i sprečavanja zloupotreba.

## Zašto ih koristimo

- da biste mogli da postavljate oglase i dopisujete se sa kupcima i prodavcima,
- da bismo odgovorili na vaše poruke i uključili plaćene usluge,
- da bismo sprečili prevare, neželjene poruke i zloupotrebu sajta.

Podatke ne prodajemo i ne ustupamo drugima radi reklamiranja.

## Šta drugi korisnici vide

Na oglasu se prikazuju ime ili naziv firme, mesto i broj telefona ako ste ga uneli u oglas. Adresa e-pošte se ne prikazuje.

## Koliko dugo čuvamo podatke

Podatke naloga čuvamo dok nalog postoji. Posle brisanja naloga brišemo i oglase i poruke, osim podataka koje moramo da čuvamo po zakonu (na primer podaci o uplatama).

## Kolačići

Sajt koristi kolačiće koji su neophodni za rad: prijavu na nalog i zaštitu formi. Bez njih prijava nije moguća.

## Vaša prava

Imate pravo da tražite uvid u svoje podatke, ispravku, brisanje i ograničenje obrade, kao i pravo da podnesete pritužbu Povereniku za informacije od javnog značaja i zaštitu podataka o ličnosti. Uputstvo za brisanje naloga je na strani [Brisanje ličnih podataka](/brisanje-licnih-podataka/).

## Podaci o plaćanju

Kada naručite plaćenu uslugu, obrađujemo podatke koji su potrebni da bismo zaključili i izvršili ugovor i ispunili obaveze iz poreskih i računovodstvenih propisa:

- **Narudžbina:** usluga, oglas na koji se odnosi, iznos, broj narudžbine (poziv na broj), datum i status, vreme kada ste prihvatili uslove i dali saglasnost da usluga počne odmah, i IP adresa sa koje ste to uradili.
- **Podaci za račun firme:** naziv, PIB, matični broj, adresa i e-pošta na koju šaljemo račun, ako plaćate kao firma ili preduzetnik.
- **Podaci o uplati:** iz izvoda banke vidimo ime i račun platioca, iznos, datum i poziv na broj. Koristimo ih samo da uplatu povežemo sa narudžbinom i, kada je potrebno, da vratimo novac. Podatke o platnim karticama ne prikupljamo.
- **Računi:** fizičkom licu izdajemo fiskalni račun preko sistema elektronske fiskalizacije Poreske uprave, uz aplikaciju ovlašćenog pružaoca usluge fiskalizacije. Na fiskalni račun ne upisujemo Vaše ime, već samo uslugu, iznos i način plaćanja. Firmi izdajemo račun sa podacima firme. Račun Vam šaljemo e-poštom.

Narudžbine, račune i podatke o uplatama čuvamo onoliko dugo koliko propisuju Zakon o računovodstvu i poreski propisi, i posle brisanja naloga. Pristup im imaju samo administratori sajta i lice koje vodi naše poslovne knjige.

## Kontakt

Za sva pitanja o podacima o ličnosti pišite nam preko [kontakt forme](/kontakt/) ili na {email}.
MD,

    /* Pravni tekstovi za plaćene usluge (nacrt, vidi docs/moduli/stranice.md) --
     * Zamene: {usluge_cenovnik} (spisak usluga i cena iz tabele products), {prodavac}
     * (podaci o prodavcu iz podešavanja payment_* i contact_*), {pdv_napomena}
     * (payment_vat_note, prazno dok nije uneto), {rok_uplate} (payments.pending_days), {telefon}. */

    'placanje.naslov' => 'Uslovi kupovine i plaćanja',
    'placanje.tekst' => <<<'MD'
Ovi uslovi važe kada na sajtu loglasi.rs naručite i platite neku od plaćenih usluga. Postavljanje oglasa je besplatno; plaća se samo ono što sami izaberete. Za korišćenje sajta važe i [Uslovi korišćenja](/uslovi-koriscenja/).

## 1. Prodavac

{prodavac}

## 2. Plaćene usluge

Trenutno možete da naručite:

{usluge_cenovnik}

Opis svake usluge i važeće cene su na strani [Cenovnik](/cenovnik/). Cena koja važi za Vašu narudžbinu je ona koja piše na strani narudžbine i na predračunu.

Ako paket Vaše firme uključuje podizanja, isticanja ili Premium XL karticu, ili ste ranije kupili paket podizanja, uslugu uključujete bez nove narudžbine i bez plaćanja.

## 3. Kako se naručuje

1. Izaberete uslugu na strani oglasa, na Cenovniku ili u svom nalogu, i oglas na koji se odnosi.
2. Izaberete da li plaćate kao fizičko lice ili kao firma (preduzetnik). Firma upisuje naziv, PIB, matični broj, adresu i e-poštu za račun.
3. Prihvatate ove uslove. Kao fizičko lice dajete i saglasnost da usluga počne odmah po evidentiranju uplate (vidite [Odustanak od ugovora](/odustanak-od-ugovora/)).
4. Potvrdite narudžbinu. Odmah dobijate predračun na ekranu i e-poštom, sa podacima za uplatu i IPS QR kodom. Sve narudžbine su u [Mom nalogu](/moj-nalog/narudzbine/).

## 4. Plaćanje

- Plaća se uplatom na račun prodavca, u dinarima. Najlakše je da u aplikaciji svoje banke izaberete plaćanje skeniranjem IPS QR koda sa predračuna. Možete da platite i nalogom za prenos u banci, pošti ili elektronskom bankarstvu.
- Poziv na broj (model 97) je broj Vaše narudžbine. Upišite ga tačno, jer po njemu povezujemo uplatu sa narudžbinom.
- Plaćanje platnom karticom za sada nije moguće.
- Ako uplata ne stigne u roku od {rok_uplate}, narudžbina se sama otkazuje. Narudžbinu koja čeka uplatu možete i sami da otkažete u svom nalogu, bez ikakvih troškova.

## 5. Cene

Sve cene su u dinarima (RSD). Iznos na predračunu je iznos koji plaćate; mi ne naplaćujemo nikakve dodatne troškove. Proviziju banke za uplatu, ako je Vaša banka naplaćuje, plaćate banci. {pdv_napomena}

## 6. Kada usluga počinje

- Usluga se uključuje kada evidentiramo uplatu po pozivu na broj. O tome Vam šaljemo e-poruku.
- Trajanje promocije i dodatnih oglasa računa se od uključenja. Ako je ista promocija na oglasu već aktivna, novo trajanje se nadovezuje na postojeće.
- Podizanje se izvrši odmah, jednom. Kupljena podizanja iz paketa koristite kada želite.
- Paket za firme kupljen tokom besplatne prve godine počinje kada besplatna godina istekne.
- Nema automatske obnove ni automatske naplate. Kada usluga istekne, sami birate da li ćete je ponovo naručiti.

## 7. Računi

- Fizičkom licu izdajemo fiskalni račun i šaljemo ga e-poštom.
- Firmi ili preduzetniku izdajemo račun (fakturu) na podatke iz narudžbine i šaljemo ga na e-poštu za račun.

## 8. Odustanak, reklamacije i povraćaj novca

- Kao potrošač imate pravo da odustanete od ugovora u roku od 14 dana, uz izuzetak za uslugu koja je izvršena. Sve o tome, i obrazac, je na strani [Odustanak od ugovora](/odustanak-od-ugovora/).
- Ako usluga nije pružena kako je opisano, podnesite reklamaciju. Kako i u kom roku odgovaramo piše na strani [Reklamacije i povraćaj novca](/reklamacije/).
- Novac vraćamo uplatom na Vaš tekući račun, najkasnije u roku od 14 dana. Za povraćaj nam je potreban broj računa na koji vraćamo novac.

## 9. Kontakt

Pitanja o narudžbinama i plaćanju pošaljite na {email}, preko [kontakt forme](/kontakt/) ili pozovite {telefon}. Uz pitanje navedite broj narudžbine.
MD,

    'odustanak.naslov' => 'Odustanak od ugovora',
    'odustanak.tekst' => <<<'MD'
Ugovor o plaćenoj usluzi na loglasi.rs zaključuje se na daljinu, preko sajta. Prema Zakonu o zaštiti potrošača („Službeni glasnik RS”, br. 88/2021), kao potrošač (fizičko lice koje uslugu kupuje za lične potrebe) imate pravo da odustanete od takvog ugovora, bez navođenja razloga.

## Rok za odustanak

Rok je 14 dana od dana zaključenja ugovora, odnosno od dana kada ste potvrdili narudžbinu. Rok je ispoštovan ako izjavu o odustanku pošaljete pre nego što istekne.

## Kada pravo na odustanak prestaje

Usluge na loglasi.rs su digitalne i počinju odmah: podizanje se izvrši u trenutku uključenja, a promocije i dodatni oglasi rade od uključenja. Zato pri naručivanju, kao fizičko lice, izričito tražite da usluga počne odmah po evidentiranju uplate i potvrđujete da znate da time gubite pravo na odustanak za izvršenu uslugu. Vreme te saglasnosti čuvamo uz narudžbinu.

- **Usluga koja je u potpunosti izvršena** (na primer obavljeno podizanje, ili promocija čije je trajanje isteklo): pravo na odustanak više ne postoji.
- **Usluga koja još traje** (istaknut oglas, TOP, Premium XL, dodatni oglasi, paket): možete da odustanete, a mi vraćamo deo cene za vreme koje niste iskoristili.
- **Narudžbina koja još nije plaćena:** ne morate da šaljete izjavu, otkažite je u svom nalogu ili samo nemojte da platite.

## Koliko novca vraćamo

- Ako uplata jeste evidentirana, a usluga još nije uključena, vraćamo ceo iznos.
- Za promocije, dodatne oglase i pakete za firme vraćamo deo cene srazmeran broju celih dana koji su ostali do kraja usluge. Dan u kome primimo izjavu o odustanku računa se kao iskorišćen. Na primer, za uslugu od 7 dana koju ste koristili 2 dana i deo trećeg vraćamo 4/7 cene.
- Za paket podizanja vraćamo deo cene srazmeran broju podizanja koja niste iskoristili.
- Pojedinačno podizanje izvrši se odmah, pa se za njega novac ne vraća.
- Ako usluga nije mogla da se pruži našom greškom, vraćamo ceo iznos, bez obzira na ova pravila.

Kada odustanete, usluga se isključuje za vreme koje nije iskorišćeno.

## Kako da odustanete

Pošaljite nam jasnu izjavu da odustajete od ugovora. Možete da koristite obrazac ispod, ali ne morate.

- **E-poštom** na {email}, sa adrese kojom ste registrovani, ili
- **poštom** na adresu prodavca iz obrasca.

Navedite broj narudžbine (poziv na broj) i broj tekućeg računa na koji da vratimo novac. Prijem izjave potvrđujemo e-poštom bez odlaganja.

## Povraćaj novca

Novac vraćamo najkasnije u roku od 14 dana od dana kada primimo izjavu o odustanku, uplatom na Vaš tekući račun, jer ste i platili prenosom na račun. Za povraćaj ne plaćate nikakvu naknadu. Uz povraćaj izdajemo i dokument o povraćaju (refundaciju fiskalnog računa ili knjižno odobrenje za firmu), vezan za prvobitni račun.

## Firme i preduzetnici

Zakonsko pravo na odustanak imaju potrošači. Firmama i preduzetnicima koji odustanu od usluge koja još traje vraćamo novac po istim pravilima, na isti način.
MD,

    'odustanak.obrazac.naslov' => 'Obrazac za odustanak od ugovora zaključenog na daljinu',
    'odustanak.obrazac.uvod' => '(popunite i pošaljite ovaj obrazac samo ako želite da odustanete od ugovora)',
    'odustanak.obrazac.primalac' => 'Primalac',
    'odustanak.obrazac.izjava' => 'Ovim obaveštavam da odustajem od ugovora o pružanju sledeće usluge:',
    'odustanak.obrazac.polja' => [
        'Usluga (naziv usluge i naslov ili broj oglasa)',
        'Broj narudžbine (poziv na broj)',
        'Datum zaključenja ugovora (datum narudžbine)',
        'Ime i prezime potrošača',
        'Adresa potrošača',
        'E-pošta ili telefon',
        'Broj tekućeg računa za povraćaj novca',
    ],
    'odustanak.obrazac.potpis' => 'Potpis potrošača (samo ako se obrazac šalje na papiru)',
    'odustanak.obrazac.datum' => 'Datum',
    'odustanak.obrazac.uputstvo' => 'Polja možete da popunite ovde, pa da obrazac odštampate ili sačuvate kao PDF i pošaljete na {email}. Možete i da odštampate prazan obrazac i da ga popunite rukom.',
    'odustanak.obrazac.stampa' => 'Odštampajte obrazac',

    'reklamacije.naslov' => 'Reklamacije i povraćaj novca',
    'reklamacije.tekst' => <<<'MD'
Ako plaćena usluga nije pružena kako je opisano, imate pravo na reklamaciju, u skladu sa Zakonom o zaštiti potrošača („Službeni glasnik RS”, br. 88/2021). Na primer: uplata je evidentirana a usluga nije uključena, oglas nije istaknut ili podignut, usluga je trajala kraće nego što je plaćeno, iznos nije tačan ili račun nije stigao.

## Kako da podnesete reklamaciju

- **E-poštom** na {email},
- preko [kontakt forme](/kontakt/),
- **poštom** na adresu prodavca navedenu ispod, ili
- **telefonom** na {telefon}; reklamaciju primljenu telefonom zapisujemo i potvrđujemo e-poštom.

## Šta nam je potrebno

- broj narudžbine (poziv na broj) i naziv usluge,
- naslov ili broj oglasa na koji se usluga odnosi,
- kratak opis problema i od kada postoji,
- šta tražite: da uslugu ispravno pružimo, da je produžimo ili da vratimo novac,
- Vaše ime i e-pošta ili telefon za odgovor,
- račun ili drugi dokaz o kupovini, na primer potvrda o uplati. Broj narudžbine je dovoljan da je pronađemo.

## Rokovi

- Prijem reklamacije potvrđujemo bez odlaganja i javljamo Vam broj pod kojim je zavedena.
- Najkasnije u roku od **8 dana** od prijema reklamacije pisanim putem Vam odgovaramo da li je prihvatamo i šta predlažemo.
- Reklamaciju rešavamo najkasnije u roku od **15 dana** od dana kada je podneta.
- Svaku reklamaciju upisujemo u evidenciju primljenih reklamacija.

## Povraćaj novca

Ako je rešenje povraćaj novca, novac vraćamo uplatom na Vaš tekući račun, najkasnije u roku od **14 dana**, bez ikakve naknade. Za povraćaj nam je potreban broj računa na koji vraćamo novac. Uz povraćaj izdajemo dokument o povraćaju vezan za prvobitni račun (refundaciju fiskalnog računa ili knjižno odobrenje za firmu).

Ako usluga nije mogla da se pruži našom greškom, vraćamo ceo iznos. Za uslugu koja je delimično pružena vraćamo deo cene za vreme koje nije iskorišćeno, kao što piše na strani [Odustanak od ugovora](/odustanak-od-ugovora/).

## Ako niste zadovoljni odgovorom

Ako reklamaciju ne prihvatimo ili niste zadovoljni rešenjem, možete da pokrenete postupak vansudskog rešavanja potrošačkog spora pred telom sa spiska tela za vansudsko rešavanje potrošačkih sporova koji vodi ministarstvo nadležno za zaštitu potrošača, ili da se obratite sudu.

## Prodavac

{prodavac}
MD,

    /* Greške (500 i 503 su u šablonu, jer se prikazuju i bez baze) -------- */

    'greska.404.naslov' => 'Strana nije pronađena',
    'greska.404.tekst' => 'Strana koju tražite ne postoji ili je premeštena. Proverite adresu ili potražite oglas pretragom.',
    'greska.410.naslov' => 'Ova strana više ne postoji',
    'greska.410.tekst' => 'Oglas ili strana su trajno uklonjeni. Pogledajte druge oglase iz Loznice i okoline.',

    /* SEO: title i opis po tipu strane (seo-sta-mora-da-ostane.md, odeljak 1) ----
     * Ručna vrednost ide doslovno; šablon dobija „ | loglasi.rs” ako ga nema.
     * Zamene: {kategorija} {naslov} {mesto} {cena} {firma} {ime} {upit} */

    'seo.home.title' => 'Besplatni oglasi Loznica – kupovina, prodaja, usluge | loglasi.rs',
    'seo.home.description' => 'loglasi.rs je lokalni oglasnik za Loznicu i okolinu: automobili, nekretnine, usluge, poslovi, mobilni telefoni i besplatne stvari. Postavite oglas besplatno.',
    'seo.archive.title' => 'Svi oglasi Loznica',
    'seo.archive.description' => 'Svi lokalni oglasi za Loznicu i okolinu na jednom mestu: automobili, nekretnine, usluge, poslovi, mobilni telefoni i besplatne stvari.',
    'seo.archive.h1' => 'Svi oglasi u Loznici',
    'seo.category.title' => '{kategorija}, oglasi Loznica',
    'seo.category.description' => 'Oglasi {o_cemu} u Loznici i okolini na loglasi.rs. Kontaktirajte prodavca direktno ili besplatno postavite svoj oglas.',
    'seo.category.h1' => '{kategorija} u Loznici',
    'seo.category.faq_title' => 'Česta pitanja – {kategorija} Loznica',
    'seo.listing.title' => '{naslov}, {kategorija}, {mesto}',
    'seo.listing.title_short' => '{naslov}, {mesto}',
    'seo.listing.description' => '{naslov} u mestu {mesto}. {cena}Pogledajte oglas i kontaktirajte prodavca na loglasi.rs.',
    'seo.business.title' => '{firma}, {mesto}',
    'seo.business.description' => 'Oglasi i kontakt firme {firma} iz mesta {mesto} na loglasi.rs.',
    'seo.person.title' => '{ime}, oglasi',
    'seo.search.title' => 'Pretraga: {upit}',
    'seo.404.title' => 'Stranica nije pronađena | loglasi.rs',
    'seo.o_nama.title' => 'O nama – lokalni oglasi za Loznicu i okolinu | loglasi.rs',
    'seo.o_nama.description' => 'loglasi.rs je besplatan sajt za lokalne oglase u Loznici, Banji Koviljači, Lešnici i okolini. Kupujte i prodajte od ljudi iz svog kraja, bez posrednika.',
    'seo.kontakt.title' => 'Kontakt – pišite nam | loglasi.rs',
    'seo.kontakt.description' => 'Imate pitanje o oglasu, nalogu ili oglašavanju firme na loglasi.rs? Pišite nam putem kontakt forme ili emaila, odgovaramo brzo.',
    'seo.faq.title' => 'Česta pitanja (FAQ) – kako postaviti oglas | loglasi.rs',
    'seo.faq.description' => 'Odgovori na najčešća pitanja: kako postaviti i obnoviti oglas, koliko traje, šta je besplatno i kako se firme oglašavaju na loglasi.rs.',
    'seo.uslovi.title' => 'Uslovi korišćenja | loglasi.rs',
    'seo.uslovi.description' => 'Pravila korišćenja sajta loglasi.rs: šta smete da oglašavate, obaveze oglašivača i kupaca, odgovornost i uklanjanje oglasa.',
    'seo.privatnost.title' => 'Politika privatnosti | loglasi.rs',
    'seo.privatnost.description' => 'Kako loglasi.rs prikuplja, koristi i čuva vaše lične podatke, koja su vaša prava i kako nas možete kontaktirati u vezi sa privatnošću.',
    'seo.placanje.title' => 'Uslovi kupovine i plaćanja | loglasi.rs',
    'seo.placanje.description' => 'Kako se naručuju i plaćaju promocije oglasa i paketi za firme na loglasi.rs: cene u dinarima, uplata preko IPS QR koda, računi i povraćaj novca.',
    'seo.odustanak.title' => 'Odustanak od ugovora i obrazac | loglasi.rs',
    'seo.odustanak.description' => 'Pravo na odustanak od ugovora zaključenog na daljinu u roku od 14 dana: kako da odustanete, kada pravo prestaje i obrazac za odustanak.',
    'seo.reklamacije.title' => 'Reklamacije i povraćaj novca | loglasi.rs',
    'seo.reklamacije.description' => 'Kako da podnesete reklamaciju za plaćenu uslugu na loglasi.rs, u kom roku odgovaramo i rešavamo reklamaciju i kada vraćamo novac.',
    'seo.brisanje.title' => 'Brisanje ličnih podataka | loglasi.rs',
    'seo.brisanje.description' => 'Uputstvo kako da zatražite brisanje svog naloga i ličnih podataka sa sajta loglasi.rs.',
    'seo.cenovnik.title' => 'Cenovnik: promocije oglasa i paketi za firme | loglasi.rs',
    'seo.cenovnik.description' => 'Postavljanje oglasa je besplatno. Pogledajte cene promocija oglasa i paketa za firme iz Loznice.',
    'seo.za_firme.title' => 'Za firme: profil firme i paketi | loglasi.rs',
    'seo.za_firme.description' => 'Predstavite svoju firmu kupcima iz Loznice: profil sa logoom, radnim vremenom, mapom i svim oglasima na jednom mestu. Prvih {besplatne_firme} firmi 12 meseci besplatno.',
];
