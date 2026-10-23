{{-- Brojač besplatnih mesta za firme: „Još 29 besplatnih mesta od 30”. --}}
<div class="lgx-brojac{{ ($mali ?? false) ? ' lgx-brojac--mali' : '' }}">
	<p class="lgx-brojac__tekst"><strong>{{ $counter['text'] }}</strong></p>
	<div class="lgx-brojac__traka" aria-hidden="true"><span style="width: {{ (int) $counter['percent'] }}%"></span></div>
	<p class="lgx-brojac__opis">{{ $counter['note'] }}</p>
</div>
