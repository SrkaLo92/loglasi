{{-- Counter of free firm places: "Još 29 besplatnih mesta od 30". --}}
<div class="lgx-counter{{ ($small ?? false) ? ' lgx-counter--small' : '' }}">
	<p class="lgx-counter__text"><strong>{{ $counter['text'] }}</strong></p>
	<div class="lgx-counter__bar" aria-hidden="true"><span style="width: {{ (int) $counter['percent'] }}%"></span></div>
	<p class="lgx-counter__desc">{{ $counter['note'] }}</p>
</div>
