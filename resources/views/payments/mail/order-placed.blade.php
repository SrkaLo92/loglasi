@php
	$details = \App\Support\Payments\PaymentDetails::forOrder($order);
	$payload = $details->ipsPayload();
	$expiresAt = \App\Services\OrderService::expiresAt($order);
	$link = url_slash('moj-nalog/narudzbine/'.$order->id);
@endphp
<!doctype html>
<html lang="sr-Latn">
<head><meta charset="utf-8"><title>Uputstvo za uplatu</title></head>
<body style="margin:0;padding:24px;background:#f6f7f5;font-family:Arial,Helvetica,sans-serif;color:#1d2b26;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;">
<tr><td style="padding:24px;">
	<p style="margin:0 0 16px;font-size:20px;font-weight:bold;">loglasi<span style="color:#379b7f;">.rs</span></p>
	@if ($order->is_test)<p style="margin:0 0 12px;padding:8px 12px;background:#fcebdf;color:#9a4215;border-radius:8px;"><strong>TEST:</strong> ovo je probna porudžbina, uplata se ne knjiži i račun je probni.</p>@endif
	<p style="margin:0 0 12px;">Zdravo {{ $order->user?->name }},</p>
	<p style="margin:0 0 16px;">porudžbina je primljena: <strong>{{ $order->product?->name }}</strong>@if ($order->listing) za oglas „{{ $order->listing->title }}”@endif. Usluga se uključuje kad stigne uplata.</p>

	@if ($details->ready())
		<table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:15px;">
			<tr><td style="color:#5b6470;">Primalac</td><td>{!! nl2br(e($details->recipient)) !!}</td></tr>
			<tr><td style="color:#5b6470;">Račun primaoca</td><td><strong>{{ $details->accountDisplay }}</strong></td></tr>
			<tr><td style="color:#5b6470;">Svrha uplate</td><td>{{ $details->purpose }}</td></tr>
			<tr><td style="color:#5b6470;">Šifra plaćanja</td><td>{{ \App\Support\Payments\PaymentDetails::paymentCode() }}</td></tr>
			<tr><td style="color:#5b6470;">Iznos</td><td><strong>{{ $details->amountLabel() }}</strong></td></tr>
			<tr><td style="color:#5b6470;">Model</td><td>{{ $details->model }}</td></tr>
			<tr><td style="color:#5b6470;">Poziv na broj</td><td><strong>{{ $details->reference }}</strong></td></tr>
		</table>
		@if ($payload)
			<p style="margin:20px 0 8px;">Plaćanje telefonom: u aplikaciji banke izaberite „IPS skeniraj” i skenirajte kod.</p>
			<p style="margin:0 0 16px;"><img src="{{ $message->embedData(\App\Support\Payments\IpsQr::png($payload), 'ips-qr.png', 'image/png') }}" alt="IPS QR kod za uplatu" width="220" height="220" style="display:block;"></p>
		@endif
	@else
		<p style="margin:0 0 16px;">Iznos: <strong>{{ $details->amountLabel() }}</strong>, model {{ $details->model }}, poziv na broj <strong>{{ $details->reference }}</strong>. Podatke za uplatu (primalac i račun) šaljemo na zahtev: odgovorite na ovu poruku ili nam pišite preko strane Kontakt.</p>
	@endif

	<p style="margin:16px 0;">Obavezno upiši poziv na broj. Usluga se uključuje čim uplata bude potvrđena; račun stiže na mejl.@if ($expiresAt) Ako uplata ne stigne do {{ $expiresAt->format('j. n. Y.') }}, narudžbina se otkazuje.@endif</p>
	@if (\App\Support\Payments\PaymentDetails::vatNote() !== '')<p style="margin:0 0 16px;color:#5b6470;font-size:14px;">{{ \App\Support\Payments\PaymentDetails::vatNote() }}</p>@endif
	<p style="margin:0 0 16px;"><a href="{{ $link }}" style="display:inline-block;background:#2b7a64;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:bold;">Pogledajte narudžbinu</a></p>
	<p style="margin:0 0 12px;color:#5b6470;font-size:13px;"><a href="{{ \App\Support\Payments\PaymentDetails::legalLink('terms') }}" style="color:#2b7a64;">Uslovi kupovine i plaćanja</a> · <a href="{{ \App\Support\Payments\PaymentDetails::legalLink('withdrawal') }}" style="color:#2b7a64;">Odustanak od ugovora</a> · <a href="{{ \App\Support\Payments\PaymentDetails::legalLink('complaints') }}" style="color:#2b7a64;">Reklamacije i povraćaj novca</a></p>
	<p style="margin:0;color:#5b6470;font-size:13px;">loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.</p>
</td></tr>
</table>
</body>
</html>
