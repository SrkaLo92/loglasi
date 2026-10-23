@php
	$details = \App\Support\Payments\PaymentDetails::forOrder($order);
	$expiresAt = \App\Services\OrderService::expiresAt($order);
@endphp
{{-- Plain text: values are printed raw (no HTML entities). --}}
Zdravo {!! $order->user?->name !!},

primili smo vašu narudžbinu: {!! $order->product?->name !!}@if ($order->listing) za oglas „{!! $order->listing->title !!}”@endif.
Usluga se uključuje kad stigne uplata.

@if ($details->ready())
Primalac: {!! str_replace(["\r\n", "\n"], ', ', $details->recipient) !!}
Račun primaoca: {!! $details->accountDisplay !!}
Svrha uplate: {!! $details->purpose !!}
Šifra plaćanja: {!! config('payments.ips_payment_code') !!}
Iznos: {!! $details->amountLabel() !!}
Model: {!! $details->model !!}
Poziv na broj: {!! $details->reference !!}
@else
Iznos: {!! $details->amountLabel() !!}
Model: {!! $details->model !!}
Poziv na broj: {!! $details->reference !!}
Podatke za uplatu (primalac i račun) šaljemo na zahtev: odgovorite na ovu poruku ili nam pišite preko strane Kontakt.
@endif

Poziv na broj upišite tačno ovako: po njemu prepoznajemo vašu uplatu.
{!! config('payments.vat_note') !!}
@if ($expiresAt)
Ako uplata ne stigne do {!! $expiresAt->format('j. n. Y.') !!}, narudžbina se otkazuje.
@endif

Narudžbina i QR kod za plaćanje telefonom: {!! url_slash('moj-nalog/narudzbine/'.$order->id) !!}

--
loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.
