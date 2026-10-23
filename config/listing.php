<?php

// Listing page and seller profiles.

return [
    // Where the owner edits the firm profile (link "Dopunite profil" on their own profile).
    'profile_edit_path' => env('LISTING_PROFILE_EDIT_PATH', 'moj-nalog'),

    // "Saveti za bezbednu kupovinu" next to the contact card.
    'safety_tips_title' => 'Saveti za bezbednu kupovinu',
    'safety_tips' => [
        'Pogledajte stvar uživo pre plaćanja i proverite da li odgovara opisu.',
        'Ne šaljite novac unapred i ne plaćajte preko linkova koje dobijete porukom.',
        'Nađite se na javnom i prometnom mestu, po danu, a za vozila i nekretnine proverite papire.',
        'Ne delite lozinke, kodove iz SMS-a ni podatke sa platne kartice.',
    ],
];
