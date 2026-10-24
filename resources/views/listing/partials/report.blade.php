@use('App\Support\Listing\Icons')
@php
	$errorsBag = $errors->getBag('report');
	$open = $errorsBag->any();
@endphp
<div class="lgo lgo-report-wrap" id="prijava">
	@if (session('report_sent'))
		<p class="lgo-alert lgo-alert--ok" role="status">Hvala, prijava je poslata. Proverićemo oglas.</p>
	@else
		<details class="lgo-report" @if ($open) open @endif>
			<summary>{{ Icons::listing('flag', 16) }}<span>Prijavi oglas</span></summary>
			<form class="lgo-report__form" method="post" action="{{ url_slash('oglas/'.$listing->slug.'/prijava') }}" novalidate>
				@csrf
				<fieldset>
					<legend>Zašto prijavljujete oglas?</legend>
					@foreach ($reasons as $key => $label)
						<label class="lgo-report__reason">
							<input type="radio" name="reason" value="{{ $key }}" @checked(old('reason') === $key) @if ($errorsBag->has('reason')) aria-invalid="true" aria-describedby="lgo-report-reason-error" @endif>
							<span>{{ $label }}</span>
						</label>
					@endforeach
					@if ($errorsBag->has('reason'))
						<p class="lgo-field-error" id="lgo-report-reason-error">{{ $errorsBag->first('reason') }}</p>
					@endif
				</fieldset>
				<label class="lgo-report__label" for="lgo-report-details">Detalji (nije obavezno)</label>
				<textarea id="lgo-report-details" name="details" rows="3" maxlength="1000" @if ($errorsBag->has('details')) aria-invalid="true" aria-describedby="lgo-report-details-error" @endif>{{ old('details') }}</textarea>
				@if ($errorsBag->has('details'))
					<p class="lgo-field-error" id="lgo-report-details-error">{{ $errorsBag->first('details') }}</p>
				@endif
				<div class="lgo-hp" aria-hidden="true">
					<label for="lgo-report-website">Sajt</label>
					<input id="lgo-report-website" type="text" name="website" tabindex="-1" autocomplete="off">
				</div>
				<button type="submit" class="lgo-btn lgo-btn--report">Pošalji prijavu</button>
			</form>
		</details>
	@endif
</div>
