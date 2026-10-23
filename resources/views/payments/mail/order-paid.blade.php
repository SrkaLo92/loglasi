@use('App\Models\Product')
{{-- Plain text: values are printed raw (no HTML entities). --}}
Zdravo {!! $order->user?->name !!},

uplata za narudžbinu {!! $order->reference !!} je primljena: {!! $order->product?->name !!}@if ($order->listing) za oglas „{!! $order->listing->title !!}”@endif.
@if (in_array($order->product?->code, [Product::FEATURED_7, Product::FEATURED_30], true) && $order->listing?->featured_until)
Oglas je istaknut do {!! $order->listing->featured_until->format('j. n. Y. \u H:i') !!}.
@elseif ($order->product?->code === Product::BUMP)
Oglas je podignut na vrh liste.
@elseif ($order->product?->code === Product::BIZNIS_12M && $order->user?->firm?->biznis_until)
Biznis paket važi do {!! $order->user->firm->biznis_until->format('j. n. Y.') !!}.
@endif

Vaše narudžbine: {!! url_slash('moj-nalog/narudzbine') !!}

Hvala što koristite loglasi.rs.
--
loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.
