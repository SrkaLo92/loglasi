{{-- "Kreditni kalkulator (informativno)": closed by default, the example is calculated on the server. --}}
@use('App\Support\Listing\Icons')
@use('App\Support\Listing\LoanCalculator')
<details class="lgo lgo-card lgo-calc lgo-o5" data-lgo-calc data-currency="{{ $loan['symbol'] }}">
	<summary class="lgo-calc-toggle">
		<span class="lgo-calc-toggle__title">{{ Icons::listing('calc') }}<span>Kreditni kalkulator (informativno)</span></span>
		<span class="lgo-calc-toggle__state"><span class="lgo-calc-toggle__open">Prikaži kalkulator</span><span class="lgo-calc-toggle__close">Sakrij kalkulator</span></span>
	</summary>
	<div class="lgo-calc__form">
		<div class="lgo-calc__row">
			<label for="lgo-calc-price">Cena ({{ $loan['symbol'] }})</label>
			<input id="lgo-calc-price" name="price" type="number" inputmode="decimal" min="0" step="1" value="{{ (int) round($loan['price']) }}" data-lgo-calc-in>
		</div>
		<div class="lgo-calc__row">
			<label for="lgo-calc-down">Učešće ({{ $loan['symbol'] }})</label>
			<input id="lgo-calc-down" name="down" type="number" inputmode="decimal" min="0" step="1" value="{{ (int) $loan['down'] }}" data-lgo-calc-in>
		</div>
		<div class="lgo-calc__row">
			<label for="lgo-calc-months">Broj rata (meseci)</label>
			<input id="lgo-calc-months" name="months" type="number" inputmode="numeric" min="1" max="360" step="1" value="{{ $loan['months'] }}" data-lgo-calc-in>
		</div>
		<div class="lgo-calc__row">
			<label for="lgo-calc-rate">Godišnja kamata (%)</label>
			<input id="lgo-calc-rate" name="rate" type="number" inputmode="decimal" min="0" max="50" step="0.1" value="{{ $loan['rate'] }}" data-lgo-calc-in>
		</div>
		<output class="lgo-calc__result" for="lgo-calc-price lgo-calc-down lgo-calc-months lgo-calc-rate" aria-live="polite">
			<span class="lgo-calc__label">Mesečna rata</span>
			<strong data-lgo-calc-monthly>{{ LoanCalculator::money($loan['monthly'], $loan['symbol']) }}</strong>
			<span class="lgo-calc__total">Ukupno sa učešćem: <span data-lgo-calc-total>{{ LoanCalculator::money($loan['total'], $loan['symbol']) }}</span></span>
		</output>
	</div>
	<p class="lgo-calc__note">Obračun je informativan. Tačne uslove kredita ili lizinga daje banka.</p>
</details>
