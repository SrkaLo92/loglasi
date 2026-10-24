{{-- Back / save buttons at the bottom of a step form. --}}
@use('App\Support\Posting\Wizard')
<div class="pst-actions">
	@if ($previous)
		<a class="pst-btn pst-btn--line" href="{{ Wizard::url($listing, $previous) }}">Nazad</a>
	@endif
	<button class="pst-btn" type="submit">{{ $label ?? 'Sačuvaj i nastavi' }}</button>
</div>
