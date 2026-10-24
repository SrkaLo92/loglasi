<?php

// Lines missing from Filament's sr_Latn translation (the rest comes from the package).
return [
    'column_manager' => [
        'position' => 'Mesto :position od :count.',
        'position_in_group' => 'Mesto :position od :count u grupi :group.',
        'reordered' => 'Kolona :column je pomerena na mesto :position od :count.',
        'reordered_deferred' => 'Kolona :column je pomerena na mesto :position od :count. Primenite kolone da sačuvate izmene.',
        'reordered_in_group' => 'Kolona :column je pomerena na mesto :position od :count u grupi :group.',
        'reordered_in_group_deferred' => 'Kolona :column je pomerena na mesto :position od :count u grupi :group. Primenite kolone da sačuvate izmene.',
        'actions' => [
            'move_up' => ['label' => 'Pomeri kolonu :column gore'],
            'move_down' => ['label' => 'Pomeri kolonu :column dole'],
            'reorder' => ['label' => 'Promeni redosled kolone'],
        ],
    ],
    'columns' => [
        'editable' => [
            'saving' => 'Čuvanje izmene...',
            'saved' => 'Izmena je sačuvana.',
            'failure' => 'Čuvanje nije potvrđeno. Osvežite stranicu pa pokušajte ponovo.',
            'unavailable' => 'Ova stavka više ne može da se menja.',
        ],
        'icon' => [
            'boolean' => [
                'true' => 'Da',
                'false' => 'Ne',
            ],
        ],
        'select' => [
            'no_options_message' => 'Nema ponuđenih opcija.',
        ],
    ],
    'actions' => [
        'reorder_record' => ['label' => 'Promeni redosled stavke :key'],
        'move_record_up' => ['label' => 'Pomeri :label gore'],
        'move_record_down' => ['label' => 'Pomeri :label dole'],
        'toggle_record_content' => ['label' => 'Prikaži ili sakrij stavku :key'],
    ],
    'loading' => 'Učitavanje...',
    'reordering' => [
        'position' => ':label, mesto :position od :count.',
        'moved' => ':label je pomereno na mesto :position od :count.',
    ],
    'result_count' => '{0} Nema rezultata|[1,*] Rezultata: :count',
];
