@use('App\Services\Payments\ProductCatalog')
@php($type = $order->product ? ProductCatalog::type($order->product) : null)
{{-- Plain text: values are printed raw (no HTML entities). --}}
@if ($order->is_test)
[TEST] Ovo je probna porudžbina: uplata se ne knjiži, račun je probni.

@endif
Zdravo {!! $order->user?->name !!},

uplata za porudžbinu {!! $order->reference !!} je potvrđena i usluga je uključena: {!! $order->product?->name !!}@if ($order->listing) za oglas „{!! $order->listing->title !!}”@endif.
@if ($type === ProductCatalog::BUMP)
Oglas je podignut na vrh liste.
@elseif ($type === ProductCatalog::BUMP_PACK)
Podizanja možete da koristite kad vam odgovara, iz liste svojih oglasa.
@elseif ($order->service_ends_at)
Usluga traje do {!! $order->service_ends_at->format('j. n. Y. \u H:i') !!}.
@endif
@if ($order->receipt_number)
Broj računa: {!! $order->receipt_number !!}
@endif
@if ($order->buyer_type === \App\Enums\BuyerType::Firm && $order->receipt_type === \App\Enums\ReceiptType::Invoice && $order->receipt_number)
Račun za preuzimanje: {!! url_slash('moj-nalog/narudzbine/'.$order->id.'/racun') !!}
@elseif ($order->receipt_url)
Provera fiskalnog računa: {!! $order->receipt_url !!}
@endif

Vaše porudžbine: {!! url_slash('moj-nalog/narudzbine') !!}

Hvala što koristite loglasi.rs.
--
loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.
