{{-- Plain text: values are printed raw (no HTML entities). --}}
Zdravo{!! $name ? ', '.$name : '' !!}!

Vaš oglas „{!! $title !!}” ističe {!! $expires !!}. Posle toga se više neće prikazivati na sajtu.
Obnova je besplatna i oglas ostaje aktivan još mesec dana.

Obnovi oglas: {!! $renewUrl !!}

Oglas: {!! $listingUrl !!}
Ako ste prodali, oglas možete da označite kao prodat u delu Moji oglasi: {!! $myListingsUrl !!}

Pozdrav, loglasi.rs
