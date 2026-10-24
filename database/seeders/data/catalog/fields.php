<?php

// Listing fields, the way kupujemprodajem and polovniautomobili ask for them. `katalog:uredi` makes the
// database match this file: label, type, filter, unit, depends_on, options (when given) and order.
// Options are labels (the value is their slug) or [value, label] / [value, label, parent] lists.
// `values` renames stored values (old => new, null deletes), for type or option changes.
// Fields not listed here are left as they are, without categories (links.php decides the categories).

$cars = require __DIR__.'/cars.php';

$numbers = fn (int $from, int $to) => array_map('strval', range($from, $to));

return [
    // ------------------------------------------------------------ goods
    'stanje' => [
        'label' => 'Stanje', 'type' => 'select', 'filter' => 'multi',
        'options' => [['novo', 'Novo'], ['kao-novo', 'Kao novo (nekorišćeno)'], ['korisceno', 'Korišćeno'], ['neispravno', 'Oštećeno ili neispravno']],
    ],
    'preuzimanje' => [
        'label' => 'Preuzimanje', 'type' => 'multiselect', 'filter' => 'multi',
        'options' => [['licno-preuzimanje', 'Lično preuzimanje'], ['dostava', 'Slanje (brza pošta)']],
    ],
    'moguca_zamena' => [
        'label' => 'Moguća zamena', 'type' => 'checkbox', 'filter' => 'select',
        'values' => ['da' => '1', 'ne' => null],
    ],

    // ------------------------------------------------------------ cars
    'marka_automobila' => [
        'label' => 'Marka', 'type' => 'select', 'filter' => 'select',
        'options' => $cars['makes'], 'values' => $cars['legacy_makes'],
    ],
    'model_automobila' => [
        'label' => 'Model', 'type' => 'select', 'filter' => 'select', 'depends_on' => 'marka_automobila',
        'options' => $cars['models'], 'values' => $cars['legacy_models'],
    ],
    'godina' => ['label' => 'Godište', 'type' => 'year', 'filter' => 'range'],
    'kilometraza' => ['label' => 'Kilometraža', 'type' => 'number', 'filter' => 'range', 'unit' => 'km'],
    'gorivo' => [
        'label' => 'Gorivo', 'type' => 'select', 'filter' => 'multi',
        'options' => ['Benzin', 'Dizel', ['benzin-gas', 'Benzin + gas (TNG)'], ['benzin-metan', 'Benzin + metan (CNG)'], ['hibrid', 'Hibridni pogon'], ['plug-in-hibrid', 'Plug-in hibrid'], ['elektricni', 'Električni pogon']],
    ],
    'kubikaza' => ['label' => 'Kubikaža', 'type' => 'number', 'filter' => 'range', 'unit' => 'cm³'],
    'snaga' => ['label' => 'Snaga motora', 'type' => 'number', 'filter' => 'range', 'unit' => 'kW'],
    'menjac' => [
        'label' => 'Menjač', 'type' => 'select', 'filter' => 'multi',
        'options' => [['manuelni-4', 'Manuelni 4 brzine'], ['manuelni-5', 'Manuelni 5 brzina'], ['manuelni-6', 'Manuelni 6 brzina'], ['automatski', 'Automatski / poluautomatski']],
    ],
    'pogon' => [
        'label' => 'Pogon', 'type' => 'select', 'filter' => 'multi',
        'options' => [['prednji', 'Prednji'], ['zadnji', 'Zadnji'], ['4x4', '4x4'], ['4x4-reduktor', '4x4 reduktor']],
    ],
    'broj_vrata' => [
        'label' => 'Broj vrata', 'type' => 'select', 'filter' => 'select',
        'options' => [['2-3', '2/3 vrata'], ['4-5', '4/5 vrata']],
    ],
    'broj_sedista' => [
        'label' => 'Broj sedišta', 'type' => 'select', 'filter' => 'multi',
        'options' => array_merge($numbers(2, 8), [['9-plus', '9 i više']]),
    ],
    'boja' => [
        'label' => 'Boja', 'type' => 'select', 'filter' => 'multi',
        'options' => ['Bela', 'Bež', 'Bordo', 'Braon', 'Crna', 'Crvena', 'Ljubičasta', 'Narandžasta', 'Plava', 'Siva', 'Srebrna', 'Teget', 'Zelena', 'Zlatna', 'Žuta', ['ostalo', 'Ostalo']],
    ],
    'klima' => [
        'label' => 'Klima', 'type' => 'select', 'filter' => 'multi',
        'options' => [['nema', 'Nema klimu'], ['manuelna', 'Manuelna klima'], ['automatska', 'Automatska klima']],
    ],
    'emisiona_klasa' => [
        'label' => 'Emisiona klasa', 'type' => 'select', 'filter' => 'multi',
        'options' => [['euro-1', 'Euro 1'], ['euro-2', 'Euro 2'], ['euro-3', 'Euro 3'], ['euro-4', 'Euro 4'], ['euro-5', 'Euro 5'], ['euro-6', 'Euro 6']],
    ],
    'ostecenje' => [
        'label' => 'Oštećenje', 'type' => 'select', 'filter' => 'multi',
        'options' => [['nije-osteceno', 'Nije oštećen'], ['osteceno-vozno', 'Oštećen, u voznom stanju'], ['osteceno-nevozno', 'Oštećen, nije u voznom stanju']],
    ],
    'poreklo' => [
        'label' => 'Poreklo vozila', 'type' => 'select', 'filter' => 'multi',
        'options' => [['domace-tablice', 'Domaće tablice'], ['strane-tablice', 'Strane tablice'], ['na-ime-kupca', 'Na ime kupca']],
    ],
    'registrovan' => ['label' => 'Registrovan', 'type' => 'checkbox', 'filter' => 'select'],
    'stanje_vozila' => ['label' => 'Stanje vozila', 'type' => 'multiselect', 'filter' => 'multi'],
    'sigurnost_vozila' => ['label' => 'Sigurnost', 'type' => 'multiselect', 'filter' => 'multi'],
    'oprema_vozila' => ['label' => 'Oprema', 'type' => 'multiselect', 'filter' => 'multi'],

    // ------------------------------------------------------------ motorcycles, trucks, vans
    'marka_motocikla' => [
        'label' => 'Marka', 'type' => 'select', 'filter' => 'select',
        'options' => ['Aprilia', 'Benelli', 'Beta', 'BMW', 'Cagiva', 'Can-Am', 'CFMoto', 'Daelim', 'Derbi', 'Ducati', 'Gas Gas', 'Gilera', 'Harley-Davidson', 'Honda', 'Husaberg', 'Husqvarna', 'Hyosung', 'Indian', 'Kawasaki', 'Keeway', 'KTM', 'Kymco', 'Lifan', 'Malaguti', 'MBK', 'Moto Guzzi', 'MV Agusta', 'Niu', 'Peugeot', 'Piaggio', 'Polaris', 'Rieju', 'Royal Enfield', 'Segway', 'Sherco', 'Super Soco', 'Suzuki', 'SYM', 'TGB', 'Tomos', 'Triumph', 'Vespa', 'Voge', 'Yamaha', 'Zontes', 'Zongshen', ['ostalo', 'Ostalo']],
    ],
    'marka_vozila' => [
        'label' => 'Marka', 'type' => 'select', 'filter' => 'select',
        'options' => ['Citroen', 'DAF', 'Dacia', 'FAP', 'Fiat', 'Ford', 'Hyundai', 'Ikarbus', 'Isuzu', 'Iveco', 'Kia', 'Knaus', 'Kögel', 'Krone', 'MAN', 'Mercedes Benz', 'Mitsubishi', 'Neoplan', 'Nissan', 'Opel', 'Peugeot', 'Piaggio', 'Renault', 'Scania', 'Schmitz', 'Setra', 'TAM', 'Tatra', 'Toyota', 'Volkswagen', 'Volvo', 'Zastava', ['ostalo', 'Ostalo']],
    ],
    'nosivost' => ['label' => 'Nosivost', 'type' => 'number', 'filter' => 'range', 'unit' => 'kg'],
    'radni_sati' => ['label' => 'Radni sati', 'type' => 'number', 'filter' => 'range', 'unit' => 'h'],
    'duzina' => ['label' => 'Dužina', 'type' => 'number', 'filter' => 'range', 'unit' => 'm'],

    // ------------------------------------------------------------ real estate
    'velicina_nekretnine' => ['label' => 'Kvadratura', 'type' => 'number', 'filter' => 'range', 'unit' => 'm²'],
    'povrsina_placa' => ['label' => 'Površina placa', 'type' => 'number', 'filter' => 'range', 'unit' => 'ar'],
    'sobe' => ['label' => 'Broj soba', 'type' => 'number', 'filter' => 'range'],
    'kupatila' => ['label' => 'Broj kupatila', 'type' => 'number', 'filter' => 'range'],
    'sprat' => [
        'label' => 'Sprat', 'type' => 'select', 'filter' => 'multi',
        'options' => array_merge(
            [['suteren', 'Suteren'], ['prizemlje', 'Prizemlje'], ['visoko-prizemlje', 'Visoko prizemlje']],
            array_map(fn ($n) => [(string) $n, $n.'. sprat'], range(1, 10)),
            [['iznad-10', 'Iznad 10. sprata'], ['potkrovlje', 'Potkrovlje']],
        ),
    ],
    'spratnost' => [
        'label' => 'Spratnost', 'type' => 'select', 'filter' => 'multi',
        'options' => [['prizemna', 'Prizemna'], ['sprat', 'Prizemlje + sprat'], ['dva-sprata', 'Dva i više spratova']],
    ],
    'grejanje' => [
        'label' => 'Grejanje', 'type' => 'select', 'filter' => 'multi',
        'options' => [['centralno', 'Centralno'], ['etazno', 'Etažno'], ['gas', 'Gas'], ['struja', 'Struja'], ['ta-pec', 'TA peć'], ['toplotna-pumpa', 'Toplotna pumpa'], ['cvrsto-gorivo', 'Čvrsto gorivo (drva, pelet)'], ['nema', 'Nema grejanje']],
    ],
    'namestenost' => [
        'label' => 'Opremljenost', 'type' => 'select', 'filter' => 'multi',
        'options' => [['namesteno', 'Namešteno'], ['polunamesteno', 'Polunamešteno'], ['prazno', 'Prazno']],
    ],
    'uknjizeno' => ['label' => 'Uknjiženo', 'type' => 'checkbox', 'filter' => 'select'],
    'godina_izgradnje' => ['label' => 'Godina izgradnje', 'type' => 'year', 'filter' => 'range'],
    'vrsta_zemljista' => [
        'label' => 'Vrsta zemljišta', 'type' => 'select', 'filter' => 'multi',
        'options' => [['gradjevinsko', 'Građevinsko'], ['poljoprivredno', 'Poljoprivredno'], ['vocnjak', 'Voćnjak ili vinograd'], ['suma', 'Šuma'], ['ostalo', 'Ostalo']],
    ],
    'karakteristike_nekretnine' => ['label' => 'Karakteristike', 'type' => 'multiselect', 'filter' => 'multi'],

    // ------------------------------------------------------------ phones, computers, TV
    'memorija' => [
        'label' => 'Memorija', 'type' => 'select', 'filter' => 'multi',
        'options' => [['8gb', '8 GB i manje'], ['16gb', '16 GB'], ['32gb', '32 GB'], ['64gb', '64 GB'], ['128gb', '128 GB'], ['256gb', '256 GB'], ['512gb', '512 GB'], ['1tb', '1 TB i više']],
    ],
    'ram' => [
        'label' => 'RAM memorija', 'type' => 'select', 'filter' => 'multi',
        'options' => [['2gb', '2 GB i manje'], ['4gb', '4 GB'], ['8gb', '8 GB'], ['16gb', '16 GB'], ['32gb', '32 GB'], ['64gb', '64 GB i više']],
    ],
    'disk' => [
        'label' => 'Disk', 'type' => 'select', 'filter' => 'multi',
        'options' => [['128gb', 'Do 128 GB'], ['256gb', '256 GB'], ['512gb', '512 GB'], ['1tb', '1 TB'], ['2tb', '2 TB i više']],
    ],
    'procesor' => [
        'label' => 'Procesor', 'type' => 'select', 'filter' => 'multi',
        'options' => [['intel-i3', 'Intel Core i3'], ['intel-i5', 'Intel Core i5'], ['intel-i7', 'Intel Core i7'], ['intel-i9', 'Intel Core i9'], ['intel-ultra', 'Intel Core Ultra'], ['intel-ostali', 'Intel Celeron / Pentium / ostali'], ['ryzen-3', 'AMD Ryzen 3'], ['ryzen-5', 'AMD Ryzen 5'], ['ryzen-7', 'AMD Ryzen 7'], ['ryzen-9', 'AMD Ryzen 9'], ['amd-ostali', 'AMD ostali'], ['apple-m', 'Apple M serija'], ['ostalo', 'Ostalo']],
    ],
    'dijagonala' => ['label' => 'Dijagonala ekrana', 'type' => 'number', 'filter' => 'range', 'unit' => 'inča'],

    // ------------------------------------------------------------ fashion, bicycles
    'velicina_odece' => [
        'label' => 'Veličina', 'type' => 'select', 'filter' => 'multi',
        'options' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', ['3xl', '3XL i veće']],
    ],
    'broj_obuce' => ['label' => 'Broj obuće', 'type' => 'number', 'filter' => 'range'],
    'velicina_tocka' => [
        'label' => 'Veličina točka', 'type' => 'select', 'filter' => 'multi',
        'options' => [['12', '12"'], ['16', '16"'], ['20', '20"'], ['24', '24"'], ['26', '26"'], ['27-5', '27,5"'], ['28', '28"'], ['29', '29"']],
    ],
    'velicina_rama' => [
        'label' => 'Veličina rama', 'type' => 'select', 'filter' => 'multi',
        'options' => ['XS', 'S', 'M', 'L', 'XL'],
    ],

    // ------------------------------------------------------------ jobs
    'tip_ugovora' => ['label' => 'Vrsta zaposlenja', 'type' => 'multiselect', 'filter' => 'multi'],
    'sati' => ['label' => 'Radno vreme', 'type' => 'select', 'filter' => 'multi'],
    'nivo_posla' => ['label' => 'Iskustvo', 'type' => 'select', 'filter' => 'multi'],
    'tip_regrutera' => ['label' => 'Oglašava', 'type' => 'select', 'filter' => 'select'],
];
