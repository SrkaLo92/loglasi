<?php

/*
 * Podrazumevani tekstovi sadržajnih strana. Admin ih menja kroz podešavanja
 * (ključ „page_text.<ključ>”); dok izmene nema, prikazuje se tekst odavde.
 *
 * Zamene u vitičastim zagradama popunjavaju se iz baze (cene iz tabele products,
 * limiti iz podešavanja), da se cena nikad ne piše ručno u tekstu:
 *   {cena_podizanje} {cena_istaknuto_7} {cena_istaknuto_30} {cena_biznis}
 *   {besplatni_oglasi} {besplatne_firme} {trajanje_oglasa} {email}
 *
 * Dugi tekstovi su Markdown (## naslov, - stavka, [tekst](/putanja/)); HTML se ne prihvata.
 */

return [

    /* Cenovnik ------------------------------------------------------------ */

    'cenovnik.naslov' => 'Cenovnik',
    'cenovnik.podnaslov' => 'Postavljanje je besplatno, plaćate samo dodatke',
    'cenovnik.uvod' => 'Oglas na loglasi.rs postavljate besplatno. Ako želite da ga vidi više ljudi ili predstavljate firmu, izaberite neku od usluga ispod.',
    'cenovnik.besplatno.naziv' => 'Običan oglas',
    'cenovnik.besplatno.stavke' => [
        'Oglas traje {trajanje_oglasa}',
        'Obnova je besplatna',
        'Bez provizije na prodaju',
    ],
    'cenovnik.opis.bump' => 'Oglas se vraća na vrh liste kao da je upravo postavljen. Korisno kad oglas potone ispod novijih.',
    'cenovnik.opis.featured_7' => 'Oglas je izdvojen na početnoj strani i u rezultatima pretrage, sa oznakom „Istaknuto”.',
    'cenovnik.opis.featured_30' => 'Isto kao 7 dana, ali ceo mesec. Najisplativije za vozila, nekretnine i skuplje stvari.',
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
        ['uslov' => null, 'pitanje' => 'Da li je postavljanje oglasa besplatno?', 'odgovor' => 'Da. Privatni korisnici mogu besplatno da postave {besplatni_oglasi} mesečno. Oglas traje {trajanje_oglasa}, a obnova je besplatna.'],
        ['uslov' => 'bump', 'pitanje' => 'Šta je podizanje oglasa?', 'odgovor' => 'Za {cena_podizanje} vaš oglas se vraća na vrh liste, kao da je upravo postavljen. Sadržaj oglasa i istorija ostaju isti.'],
        ['uslov' => 'featured_7', 'pitanje' => 'Šta dobijam kad istaknem oglas?', 'odgovor' => 'Istaknut oglas se prikazuje u izdvojenim oglasima na početnoj strani i u rezultatima pretrage, sa oznakom „Istaknuto”. Isticanje na 7 dana košta {cena_istaknuto_7}, a 30 dana {cena_istaknuto_30}.'],
        ['uslov' => null, 'pitanje' => 'Kada se usluga uključuje posle uplate?', 'odgovor' => 'Čim vidimo uplatu na računu, obično istog ili sledećeg radnog dana. Ako pošaljete potvrdu o uplati, lakše ćemo je povezati sa vašim oglasom.'],
        ['uslov' => null, 'pitanje' => 'Da li mogu da platim karticom?', 'odgovor' => 'Za sada ne. Plaća se uplatom na račun, u banci, pošti ili kroz mobilno bankarstvo.'],
        ['uslov' => 'biznis_12m', 'pitanje' => 'Koliko košta Biznis paket za firme?', 'odgovor' => '{ponuda_firme} Detalji su na strani [Za firme](/za-firme/).'],
    ],

    /* Za firme ------------------------------------------------------------ */

    'za_firme.oznaka' => 'Biznis paket',
    'za_firme.naslov' => 'Predstavite svoju firmu kupcima iz Loznice',
    'za_firme.uvod' => 'Profil firme sa logoom, radnim vremenom, mapom i svim vašim oglasima na jednom mestu. Kupci vas pozivaju jednim dodirom, a vi vidite koliko ljudi vas je pronašlo.',
    'za_firme.prednosti.naslov' => 'Šta dobijate uz Biznis paket',
    'za_firme.prednosti' => [
        ['ikonica' => 'image', 'naslov' => 'Logo i naslovna slika', 'tekst' => 'Profil izgleda kao izlog vaše radnje, a ne kao nalog privatnog lica.'],
        ['ikonica' => 'badge', 'naslov' => 'Oznaka „Proverena firma”', 'tekst' => 'Podatke o firmi proveravamo pre uključenja, pa kupci znaju s kim posluju.'],
        ['ikonica' => 'clock', 'naslov' => 'Radno vreme i mapa', 'tekst' => 'Kupci odmah vide kada radite i kako da vas pronađu.'],
        ['ikonica' => 'grid', 'naslov' => 'Svi oglasi na jednom mestu', 'tekst' => 'Cela ponuda firme na jednoj adresi koju možete da podelite na mrežama.'],
        ['ikonica' => 'phone', 'naslov' => 'Pozovi, WhatsApp, Viber', 'tekst' => 'Dugmad za kontakt su pri vrhu profila i na svakom vašem oglasu.'],
        ['ikonica' => 'chart', 'naslov' => 'Statistika', 'tekst' => 'Pregledi profila i oglasa i broj kontakata, da znate šta donosi kupce.'],
    ],
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

    /* Kontakt ------------------------------------------------------------- */

    'kontakt.naslov' => 'Kontakt',
    'kontakt.uvod' => 'Imate pitanje o oglasu, plaćanju ili Biznis paketu? Pišite nam i odgovorićemo vam što pre, obično istog ili sledećeg radnog dana.',
    'kontakt.forma.naslov' => 'Pišite nam',
    'kontakt.poslato' => 'Hvala na poruci. Poslata je, a odgovor stiže na adresu e-pošte koju ste uneli.',
    'kontakt.teme' => [
        'Pitanje o oglasu',
        'Prijava firme (Biznis paket)',
        'Plaćanje i isticanje oglasa',
        'Prijava problema',
        'Nešto drugo',
    ],

    /* O nama -------------------------------------------------------------- */

    'o_nama.naslov' => 'O nama',
    'o_nama.tekst' => <<<'MD'
loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu. Napravljen je da ljudi iz našeg kraja lako prodaju, kupe ili ponude uslugu nekome ko je blizu, bez posrednika i bez provizije.

## Zašto lokalni oglasi

Na velikim sajtovima oglas iz Loznice se izgubi među hiljadama oglasa iz cele Srbije. Ovde su prodavci i kupci iz Loznice, Banje Koviljače, Lešnice, Tršića i okolnih sela, pa stvar možete da pogledate uživo pre nego što je kupite, a dogovor je brz.

## Šta možete da radite na sajtu

- Besplatno postavite {besplatni_oglasi} mesečno. Oglas traje {trajanje_oglasa}, a obnova je besplatna.
- Javite se prodavcu pozivom, porukom preko sajta, WhatsApp-om ili Viber-om.
- Sačuvajte pretragu i vratite se oglasima koji vas zanimaju.
- Ako imate firmu, otvorite profil firme sa logoom, radnim vremenom i svim oglasima na jednom mestu. Više o tome na strani [Za firme](/za-firme/).

## Kako zarađujemo

Postavljanje oglasa je besplatno. Plaćaju se samo dodaci koje sami izaberete: podizanje oglasa, isticanje oglasa i Biznis paket za firme. Sve cene su na strani [Cenovnik](/cenovnik/).

## Kontakt

Pitanja, predlozi i prijave problema su dobrodošli. Pišite nam preko [kontakt forme](/kontakt/) ili na {email}.
MD,

    /* Česta pitanja ------------------------------------------------------- */

    'faq.naslov' => 'Česta pitanja',
    'faq.uvod' => 'Odgovori na pitanja koja nam najčešće postavljate. Ako ne nađete odgovor, [pišite nam](/kontakt/).',
    'faq.pitanja' => [
        ['pitanje' => 'Da li je postavljanje oglasa besplatno?', 'odgovor' => 'Da. Privatni korisnici mogu besplatno da postave {besplatni_oglasi} mesečno. Plaćaju se samo dodaci koje sami izaberete, kao što su podizanje i isticanje oglasa.'],
        ['pitanje' => 'Kako da postavim oglas?', 'odgovor' => 'Prijavite se ili napravite nalog, pa kliknite na „Postavite oglas”. Izaberite kategoriju, upišite naslov, opis i cenu, dodajte fotografije i objavite oglas.'],
        ['pitanje' => 'Koliko dugo traje oglas?', 'odgovor' => 'Oglas traje {trajanje_oglasa}. Kad istekne, možete besplatno da ga obnovite iz svog naloga.'],
        ['pitanje' => 'Kako da kontaktiram prodavca?', 'odgovor' => 'Na strani oglasa su dugmad za poziv i poruku preko sajta, a ako je prodavac ostavio broj, i za WhatsApp i Viber.'],
        ['pitanje' => 'Kako da istaknem ili podignem oglas?', 'odgovor' => 'Podizanje oglasa košta {cena_podizanje}, a isticanje {cena_istaknuto_7} za 7 dana ili {cena_istaknuto_30} za 30 dana. Plaća se uplatom na račun, a postupak je opisan na strani [Cenovnik](/cenovnik/).'],
        ['pitanje' => 'Šta je Biznis paket?', 'odgovor' => 'Biznis paket je profil firme sa logoom, radnim vremenom, mapom i oznakom „Firma” na svakom oglasu. {ponuda_firme} Više na strani [Za firme](/za-firme/).'],
        ['pitanje' => 'Kako da prijavim sumnjiv oglas?', 'odgovor' => 'Na strani oglasa kliknite na „Prijavi oglas” i ukratko napišite šta nije u redu. Svaku prijavu pregledamo.'],
        ['pitanje' => 'Kako da kupujem bezbedno?', 'odgovor' => 'Robu pogledajte uživo pre plaćanja, ne plaćajte unapred nepoznatim osobama i nikome ne šaljite kodove koje dobijete SMS porukom.'],
        ['pitanje' => 'Kako da obrišem nalog i svoje podatke?', 'odgovor' => 'Uputstvo je na strani [Brisanje podataka](/brisanje-podataka/). Možete i da nam pišete preko [kontakt forme](/kontakt/).'],
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

- Privatni korisnici mogu besplatno da postave {besplatni_oglasi} mesečno, a firme prema svom paketu.
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

- Podizanje oglasa ({cena_podizanje}), isticanje oglasa ({cena_istaknuto_7} za 7 dana, {cena_istaknuto_30} za 30 dana) i Biznis paket ({cena_biznis} godišnje) plaćaju se uplatom na račun, po cenama sa strane [Cenovnik](/cenovnik/).
- Usluga se uključuje ručno, kada uplata stigne. Trajanje se računa od dana uključenja.
- Ako usluga ne može da se uključi, javićemo vam se i dogovoriti povraćaj uplate.

## 7. Odgovornost

Za sadržaj oglasa odgovara onaj ko ga je postavio. loglasi.rs ne odgovara za tačnost oglasa, kvalitet robe ni za dogovor između kupca i prodavca. Preporučujemo da robu pogledate uživo i da ne plaćate unapred nepoznatim osobama.

## 8. Izmene uslova

Uslove možemo da izmenimo. Nova verzija važi od objave na ovoj strani.

## 9. Kontakt

Pitanja o ovim uslovima pošaljite preko [kontakt forme](/kontakt/) ili na {email}.
MD,

    /* Politika privatnosti ------------------------------------------------ */

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

Imate pravo da tražite uvid u svoje podatke, ispravku, brisanje i ograničenje obrade, kao i pravo da podnesete pritužbu Povereniku za informacije od javnog značaja i zaštitu podataka o ličnosti. Uputstvo za brisanje naloga je na strani [Brisanje podataka](/brisanje-podataka/).

## Kontakt

Za sva pitanja o podacima o ličnosti pišite nam preko [kontakt forme](/kontakt/) ili na {email}.
MD,

    /* Greške (500 i 503 su u šablonu, jer se prikazuju i bez baze) -------- */

    'greska.404.naslov' => 'Strana nije pronađena',
    'greska.404.tekst' => 'Strana koju tražite ne postoji ili je premeštena. Proverite adresu ili potražite oglas pretragom.',
    'greska.410.naslov' => 'Ova strana više ne postoji',
    'greska.410.tekst' => 'Oglas ili strana su trajno uklonjeni. Pogledajte druge oglase iz Loznice i okoline.',
];
