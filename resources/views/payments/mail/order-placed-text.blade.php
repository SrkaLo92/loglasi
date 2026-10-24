@php
	$details = \App\Support\Payments\PaymentDetails::forOrder($order);
	$expiresAt = \App\Services\OrderService::expiresAt($order);
@endphp
{{-- Plain text: values are printed raw (no HTML entities). --}}
@if ($order->is_test)
[TEST] Ovo je probna porudžbina: uplata se ne knjiži, račun je probni.

@endif
Zdravo {!! $order->user?->name !!},

porudžbina je primljena: {!! $order->product?->name !!}@if ($order->listing) za oglas „{!! $order->listing->title !!}”@endif.
Usluga se uključuje kad stigne uplata.

@if ($details->ready())
Primalac: {!! str_replace(["\r\n", "\n"], ', ', $details->recipient) !!}
Račun primaoca: {!! $details->accountDisplay !!}
Svrha uplate: {!! $details->purpose !!}
Šifra plaćanja: {!! \App\Support\Payments\PaymentDetails::paymentCode() !!}
Iznos: {!! $details->amountLabel() !!}
Model: {!! $details->model !!}
Poziv na broj: {!! $details->reference !!}
@else
Iznos: {!! $details->amountLabel() !!}
Model: {!! $details->model !!}
Poziv na broj: {!! $details->reference !!}
Podatke za uplatu (primalac i račun) šaljemo na zahtev: odgovorite na ovu poruku ili nam pišite preko strane Kontakt.
@endif

Obavezno upišite poziv na broj. Usluga se uključuje čim uplata bude potvrđena; račun stiže na mejl.
@if (\App\Support\Payments\PaymentDetails::vatNote() !== '')
{!! \App\Support\Payments\PaymentDetails::vatNote() !!}
@endif
@if ($expiresAt)
Ako uplata ne stigne do {!! $expiresAt->format('j. n. Y.') !!}, narudžbina se otkazuje.
@endif

Narudžbina i QR kod za plaćanje telefonom: {!! url_slash('moj-nalog/narudzbine/'.$order->id) !!}

Uslovi kupovine i plaćanja: {!! \App\Support\Payments\PaymentDetails::legalLink('terms') !!}
Odustanak od ugovora: {!! \App\Support\Payments\PaymentDetails::legalLink('withdrawal') !!}
Reklamacije i povraćaj novca: {!! \App\Support\Payments\PaymentDetails::legalLink('complaints') !!}

--
loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.
