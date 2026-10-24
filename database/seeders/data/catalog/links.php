<?php

// Which fields each category asks for, by category slug. A rule names categories (`in`) or every last-level
// category under a main one (`under`, minus `except`); the fields go there in this order, `required` marks the
// mandatory ones. Rules add up, so a category can take the car fields and then "Stanje".
// `katalog:uredi` makes category_field match this file for every category (a category no rule names has no fields).

$carBodies = ['automobili-dzip-suv', 'automobili-hecbek', 'automobili-kabriolet', 'automobili-karavan', 'automobili-kupe', 'automobili-limuzina', 'automobili-minivan', 'automobili-pickup'];
$computers = ['kompjuteri', 'polovni-kompjuteri', 'mini-pc', 'apple-desktop', 'laptopovi', 'laptopovi-apple', 'netbukovi'];
$tablets = ['tableti', 'ipad'];
$phonesExcept = ['mobilni-telefoni-delovi-i-oprema'];
$noCondition = ['nekretnine', 'poslovi', 'usluge', 'kucni-ljubimci', 'poljoprivreda-domace-zivotinje'];
$motorcycles = ['atv-i-quad', 'chopper-i-cruiser', 'cross-i-enduro', 'elektricni-motocikli', 'mopedi', 'oldtajmeri', 'skuteri', 'sportski-motori'];
$trucks = ['autobusi', 'kamioni-do-7-5-tona', 'kamioni-preko-7-5-tona', 'kamperi', 'kombi-vozila', 'prikolice-i-poluprikolice'];
$vehicles = array_merge($carBodies, $motorcycles, $trucks);

return [
    // Cars (the subcategories are the body types)
    ['in' => $carBodies, 'fields' => [
        'marka_automobila', 'model_automobila', 'godina', 'kilometraza', 'gorivo', 'kubikaza', 'snaga', 'menjac',
        'pogon', 'broj_vrata', 'broj_sedista', 'boja', 'klima', 'emisiona_klasa', 'ostecenje', 'poreklo', 'registrovan',
        'moguca_zamena', 'stanje_vozila', 'sigurnost_vozila', 'oprema_vozila',
    ], 'required' => ['marka_automobila', 'godina']],
    ['in' => ['auto-delovi'], 'fields' => ['marka_automobila', 'model_automobila']],

    // Motorcycles
    ['in' => $motorcycles, 'fields' => [
        'marka_motocikla', 'godina', 'kilometraza', 'kubikaza', 'snaga', 'boja', 'registrovan', 'moguca_zamena',
    ], 'required' => ['marka_motocikla']],

    // Trucks, vans, buses, campers, trailers
    ['in' => ['kombi-vozila', 'kamioni-do-7-5-tona', 'kamioni-preko-7-5-tona', 'autobusi', 'kamperi'], 'fields' => [
        'marka_vozila', 'godina', 'kilometraza', 'gorivo', 'kubikaza', 'snaga', 'menjac', 'nosivost', 'broj_sedista',
        'emisiona_klasa', 'registrovan', 'moguca_zamena',
    ], 'required' => ['marka_vozila']],
    ['in' => ['prikolice-i-poluprikolice'], 'fields' => ['marka_vozila', 'godina', 'nosivost', 'registrovan']],

    // Boats, farm machines
    ['in' => ['camci', 'camci-gumeni-i-rib', 'splavovi', 'kajaci-i-kanui'], 'fields' => ['godina', 'duzina']],
    ['in' => ['motori-brodski-i-vanbrodski'], 'fields' => ['godina', 'snaga', 'radni_sati']],
    ['in' => ['traktori', 'kombajni', 'motokultivatori'], 'fields' => ['godina', 'snaga', 'radni_sati']],
    ['in' => ['prikljucne-masine', 'prikolice'], 'fields' => ['godina']],

    // Real estate
    ['in' => ['stanovi-prodaja', 'stanovi-iznajmljivanje', 'apartmani-prodaja', 'apartmani-iznajmljivanje'], 'fields' => [
        'velicina_nekretnine', 'sobe', 'kupatila', 'sprat', 'grejanje', 'namestenost', 'godina_izgradnje', 'uknjizeno', 'karakteristike_nekretnine',
    ], 'required' => ['velicina_nekretnine']],
    ['in' => ['kuce-prodaja', 'kuce-iznajmljivanje'], 'fields' => [
        'velicina_nekretnine', 'povrsina_placa', 'sobe', 'kupatila', 'spratnost', 'grejanje', 'namestenost', 'godina_izgradnje', 'uknjizeno', 'karakteristike_nekretnine',
    ], 'required' => ['velicina_nekretnine']],
    ['in' => ['poslovni-prostor-prodaja', 'poslovni-prostor-iznajmljivanje'], 'fields' => [
        'velicina_nekretnine', 'sprat', 'grejanje', 'godina_izgradnje', 'uknjizeno', 'karakteristike_nekretnine',
    ], 'required' => ['velicina_nekretnine']],
    ['in' => ['zemljiste'], 'fields' => ['povrsina_placa', 'vrsta_zemljista', 'uknjizeno'], 'required' => ['povrsina_placa']],

    // Jobs
    ['under' => 'poslovi', 'fields' => ['tip_ugovora', 'sati', 'nivo_posla', 'tip_regrutera']],

    // Phones, computers, TV
    ['under' => 'mobilni-telefoni', 'except' => $phonesExcept, 'fields' => ['memorija']],
    ['in' => $tablets, 'fields' => ['memorija']],
    ['in' => $computers, 'fields' => ['procesor', 'ram', 'disk']],
    ['in' => ['laptopovi', 'laptopovi-apple', 'netbukovi', 'monitori', 'tv-lcd-plazma-led', 'tv-crt-televizori', 'tableti', 'ipad'], 'fields' => ['dijagonala']],

    // Fashion, bicycles
    ['in' => ['odeca-muska', 'odeca-zenska', 'biciklisticka-odeca', 'lov-odeca-i-obuca', 'ribolov-odeca-i-obuca', 'htz-odeca-i-obuca'], 'fields' => ['velicina_odece']],
    ['in' => ['obuca-muska', 'obuca-zenska', 'obuca-decja', 'biciklisticka-obuca'], 'fields' => ['broj_obuce']],
    ['under' => 'bicikli', 'fields' => ['velicina_tocka', 'velicina_rama']],

    // Condition and delivery for everything that is a thing (not real estate, jobs, services, animals)
    ['under' => '*', 'except' => $noCondition, 'fields' => ['stanje'], 'required' => ['stanje']],
    ['under' => '*', 'except' => array_merge($noCondition, $vehicles, ['plovni-objekti']), 'fields' => ['preuzimanje']],
    ['under' => '*', 'except' => array_merge($noCondition, ['besplatne-stvari']), 'fields' => ['moguca_zamena']],
];
