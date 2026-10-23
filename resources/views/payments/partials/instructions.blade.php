{{-- Bank transfer details for one order ($details: App\Support\Payments\PaymentDetails). --}}
<dl class="lgp-pay">
	<div><dt>Primalac</dt><dd>{!! nl2br(e($details->recipient)) !!}</dd></div>
	<div><dt>Račun primaoca</dt><dd class="lgp-mono">{{ $details->accountDisplay }}</dd></div>
	<div><dt>Svrha uplate</dt><dd>{{ $details->purpose }}</dd></div>
	<div><dt>Šifra plaćanja</dt><dd class="lgp-mono">{{ config('payments.ips_payment_code') }}</dd></div>
	<div class="lgp-pay__amount"><dt>Iznos</dt><dd>{{ $details->amountLabel() }}</dd></div>
	<div><dt>Model</dt><dd class="lgp-mono">{{ $details->model }}</dd></div>
	<div class="lgp-pay__ref"><dt>Poziv na broj</dt><dd class="lgp-mono">{{ $details->reference }}</dd></div>
</dl>
