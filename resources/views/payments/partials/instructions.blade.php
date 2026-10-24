{{-- Bank transfer details for one order ($details: App\Support\Payments\PaymentDetails). --}}
<dl class="lgpay-pay">
	<div><dt>Primalac</dt><dd>{!! nl2br(e($details->recipient)) !!}</dd></div>
	<div><dt>Račun primaoca</dt><dd class="lgpay-mono">{{ $details->accountDisplay }}</dd></div>
	<div><dt>Svrha uplate</dt><dd>{{ $details->purpose }}</dd></div>
	<div><dt>Šifra plaćanja</dt><dd class="lgpay-mono">{{ config('payments.ips_payment_code') }}</dd></div>
	<div class="lgpay-pay__amount"><dt>Iznos</dt><dd>{{ $details->amountLabel() }}</dd></div>
	<div><dt>Model</dt><dd class="lgpay-mono">{{ $details->model }}</dd></div>
	<div class="lgpay-pay__ref"><dt>Poziv na broj</dt><dd class="lgpay-mono">{{ $details->reference }}</dd></div>
</dl>
