@use('App\Enums\ReceiptType')
@use('App\Support\Settings')
@php
	$credit = $type === ReceiptType::InvoiceCredit;
	$title = ($credit ? 'Knjižno odobrenje ' : 'Račun ').$number;
	$money = fn (int $rsd) => number_format($rsd, 2, ',', '.').' RSD';
	$vatNote = trim((string) Settings::get('payment_vat_note')) ?: 'Obveznik nije u sistemu PDV-a.';
	$item = trim(($order->product?->name ?? 'Usluga oglašavanja').($order->listing ? ' za oglas „'.$order->listing->title.'”' : ''));
@endphp
<!doctype html>
<html lang="sr-Latn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $title }} | loglasi.rs</title>
<style>
	:root { color-scheme: light; --ink: #1d232b; --muted: #5b6573; --line: #d9dee5; }
	* { box-sizing: border-box; }
	body { margin: 0; background: #eef1f4; color: var(--ink); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
	.doc { max-width: 820px; margin: 24px auto; padding: 40px; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgb(0 0 0 / .06); }
	.bar { max-width: 820px; margin: 16px auto 0; padding: 0 16px; display: flex; gap: 12px; justify-content: space-between; align-items: center; }
	.bar a { color: var(--ink); }
	.bar button { min-height: 44px; padding: 0 20px; border: 0; border-radius: 8px; background: #1d232b; color: #fff; font: inherit; font-weight: 600; cursor: pointer; }
	header { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; border-bottom: 2px solid var(--ink); padding-bottom: 16px; }
	h1 { margin: 0; font-size: 26px; }
	.meta { text-align: right; color: var(--muted); }
	.meta strong { color: var(--ink); }
	.parties { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 24px 0; }
	.parties h2 { margin: 0 0 6px; font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
	.parties dl { margin: 0; display: grid; grid-template-columns: max-content 1fr; gap: 2px 12px; }
	.parties dt { color: var(--muted); }
	.parties dd { margin: 0; }
	table { width: 100%; border-collapse: collapse; margin-top: 8px; }
	th, td { padding: 10px 8px; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
	th { font-size: 13px; color: var(--muted); font-weight: 600; }
	.num { text-align: right; white-space: nowrap; }
	tfoot td { border-bottom: 0; font-weight: 700; font-size: 17px; }
	.notes { margin-top: 24px; color: var(--muted); font-size: 14px; }
	.notes p { margin: 4px 0; }
	@media (max-width: 640px) { .doc { margin: 0; padding: 20px 16px; border-radius: 0; } .parties { grid-template-columns: 1fr; } header { flex-direction: column; } .meta { text-align: left; } }
	@media print { body { background: #fff; } .bar { display: none; } .doc { margin: 0; padding: 0; box-shadow: none; max-width: none; } }
</style>
</head>
<body>
<div class="bar">
	<a href="{{ url_slash('moj-nalog/narudzbine/'.$order->id) }}">‹ Nazad na narudžbinu</a>
	<button type="button" data-print>Odštampaj ili sačuvaj PDF</button>
</div>
<main class="doc">
	<header>
		<div>
			<h1>{{ $credit ? 'Knjižno odobrenje' : 'Račun' }} br. {{ $number }}</h1>
			@if ($order->is_test)
				<p><strong>PROBNI DOKUMENT, nema pravno dejstvo.</strong></p>
			@endif
		</div>
		<div class="meta">
			<div>Datum izdavanja: <strong>{{ $issuedAt->format('d.m.Y.') }}</strong></div>
			<div>Mesto izdavanja: <strong>Loznica</strong></div>
			@if (! $credit && $order->paid_at)
				<div>Datum prometa: <strong>{{ $order->paid_at->format('d.m.Y.') }}</strong></div>
			@endif
		</div>
	</header>

	<section class="parties">
		<div>
			<h2>Prodavac</h2>
			<dl>
				@forelse ($seller as $row)
					<dt>{{ $row['label'] }}</dt><dd>{{ $row['value'] }}</dd>
				@empty
					<dt>Naziv</dt><dd>loglasi.rs</dd>
				@endforelse
			</dl>
		</div>
		<div>
			<h2>Kupac</h2>
			<dl>
				<dt>Naziv</dt><dd>{{ $order->buyer_name }}</dd>
				@if ($order->buyer_address)<dt>Adresa</dt><dd>{{ $order->buyer_address }}</dd>@endif
				@if ($order->buyer_pib)<dt>PIB</dt><dd>{{ $order->buyer_pib }}</dd>@endif
				@if ($order->buyer_mb)<dt>Matični broj</dt><dd>{{ $order->buyer_mb }}</dd>@endif
			</dl>
		</div>
	</section>

	<table>
		<thead>
			<tr><th>Opis</th><th class="num">Kol.</th><th class="num">Cena</th><th class="num">Iznos</th></tr>
		</thead>
		<tbody>
			<tr>
				<td>{{ $credit ? 'Povraćaj za: ' : '' }}{{ $item }}</td>
				<td class="num">1</td>
				<td class="num">{{ $money($amount) }}</td>
				<td class="num">{{ $money($amount) }}</td>
			</tr>
		</tbody>
		<tfoot>
			<tr><td colspan="3">{{ $credit ? 'Ukupno za povraćaj' : 'Ukupno za uplatu' }}</td><td class="num">{{ $money($amount) }}</td></tr>
		</tfoot>
	</table>

	<div class="notes">
		<p>{{ $vatNote }}</p>
		@if ($credit)
			<p>Odobrenje se odnosi na račun br. {{ $order->receipt_number }} od {{ ($order->receipt_issued_at ?? $order->paid_at)?->format('d.m.Y.') }}.</p>
		@else
			<p>Plaćeno uplatom na račun{{ $order->paid_at ? ' dana '.$order->paid_at->format('d.m.Y.') : '' }}, poziv na broj {{ $order->reference }}.</p>
		@endif
		<p>Dokument je izrađen elektronski i punovažan je bez pečata i potpisa.</p>
	</div>
</main>
<script src="{{ asset_v('js/print.js') }}" defer></script>
</body>
</html>
