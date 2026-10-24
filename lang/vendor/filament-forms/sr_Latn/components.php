<?php

// Lines missing from Filament's sr_Latn translation (the rest comes from the package).
// The rich editor and builder lines are not used in this panel and stay in English.
return [
    'checkbox_list' => ['required_description' => 'Izaberite bar jednu opciju.'],
    'color_picker' => ['panel_label' => 'Izbor boje'],
    'date_time_picker' => [
        'month_select' => ['label' => 'Mesec'],
        'year_input' => ['label' => 'Godina'],
        'hour_input' => ['label' => 'Sat'],
        'minute_input' => ['label' => 'Minut'],
        'second_input' => ['label' => 'Sekund'],
    ],
    'file_upload' => [
        'placeholder' => 'Nema otpremljenih fajlova',
        'actions' => [
            'download' => ['label' => 'Preuzmi'],
            'open' => ['label' => 'Otvori u novoj kartici'],
        ],
        'editor' => ['label' => 'Uređivač slike'],
    ],
    'key_value' => [
        'actions' => [
            'move_down' => ['label' => 'Pomeri dole'],
            'move_up' => ['label' => 'Pomeri gore'],
        ],
        'columns' => [
            'actions' => ['label' => 'Akcije'],
            'reorder' => ['label' => 'Redosled'],
        ],
        'reordering' => [
            'row' => 'Red',
            'position' => ':label, mesto :position od :count',
            'moved' => ':label je pomereno na mesto :position od :count',
        ],
    ],
    'repeater' => [
        'reordering' => [
            'item' => 'Stavka',
            'position' => ':label, mesto :position od :count',
            'moved' => ':label je pomereno na mesto :position od :count',
        ],
        'columns' => [
            'actions' => ['label' => 'Akcije'],
            'reorder' => ['label' => 'Redosled'],
        ],
    ],
    'select' => [
        'actions' => [
            'clear' => ['label' => 'Poništi izbor'],
            'remove_option' => ['label' => 'Ukloni :label'],
        ],
        'error_message' => 'Opcije nisu učitane. Pokušajte ponovo.',
        'no_options_message' => 'Nema ponuđenih opcija.',
        'required_description' => 'Izaberite opciju.',
        'search_label' => 'Pretraga',
    ],
    'tags_input' => [
        'actions' => [
            'delete' => ['label' => 'Obriši'],
        ],
        'tag_added' => 'Dodato: :tag',
        'tag_removed' => 'Uklonjeno: :tag',
    ],
    'toggle_buttons' => ['required_description' => 'Izaberite bar jednu opciju.'],
];
