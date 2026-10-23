{{--
	"Biznis paket" order button with the package status. For /moj-nalog/firma/ and /cenovnik/:
	@include('payments.partials.biznis-cta')
	Optional: 'firm' => $firm (otherwise the signed-in user's firm), 'label' => button text.
--}}
@use('App\Models\Product')
@use('App\Support\Stranice\Cenovnik')
@php
	$biznisProduct = Cenovnik::product(Product::BIZNIS_12M);
	$firm ??= auth()->user()?->firm;
@endphp
@if ($biznisProduct)
	<div class="lgp-biznis">
		@if ($firm?->hasBiznis())
			<p class="lgp-biznis__status">Biznis paket važi do {{ $firm->biznis_until->format('j. n. Y.') }}.</p>
		@endif
		<a class="lg-btn lg-btn--green" href="{{ url_slash('moj-nalog/narudzbine/nova') }}?usluga={{ Product::BIZNIS_12M }}">{{ $label ?? ($firm?->hasBiznis() ? 'Produžite Biznis paket' : 'Naručite Biznis paket') }}</a>
	</div>
@endif
