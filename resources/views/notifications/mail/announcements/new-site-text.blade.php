{{-- Plain text: values are printed raw (no HTML entities). --}}
Zdravo{!! $name !== '' ? ' '.$name : '' !!},

novi loglasi.rs je stigao!

Potpuno smo ga napravili iznova, da ti prodaja i kupovina u Loznici i okolini budu lakše nego ikad:

- Oglas za minut, sa telefona: slikaš, upišeš cenu, objaviš.
- {!! \Illuminate\Support\Str::ucfirst($free) !!} besplatno. Oglas traje {!! $days !!} dana i obnoviš ga jednim klikom, opet besplatno.
- Poruke na sajtu: kupci ti pišu direktno, bez deljenja broja ako to ne želiš.
- Sačuvana pretraga: javljamo ti čim se pojavi ono što tražiš.
- Firme iz Loznice: sve lokalne firme i njihova ponuda na jednom mestu: {!! $firmsUrl !!}

Tvoj nalog je ostao isti. Prijavljuješ se istim mejlom i istom lozinkom. Ako si zaboravio lozinku, novu postaviš ovde: {!! $resetUrl !!}

Postavi oglas: {!! $postUrl !!}
Prijava: {!! $loginUrl !!}

Uskoro i u telefonu: stiže aplikacija loglasi.rs za Android i iPhone. Javićemo ti čim bude u prodavnicama.

Imaš nešto što ti ne treba? Sad je pravi trenutak. Ko oglasi, taj i proda.

Pozdrav iz Loznice,
Srđan i tim loglasi.rs

--
loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu. {!! $homeUrl !!}
Ovaj mejl dobijaš jer imaš nalog na loglasi.rs. Odjava: {!! $unsubscribeUrl !!}
