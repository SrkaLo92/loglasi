@extends('posting.frame')

@section('step')
<form class="pst-card pst-form" method="post" action="{{ \App\Support\Posting\Wizard::url($listing, 'contact') }}" novalidate>
	@csrf
	@method('PUT')
	<h2>Kontakt</h2>
	<div class="pst-field">
		<label for="pst-phone">Broj telefona za ovaj oglas</label>
		<input id="pst-phone" type="tel" name="contact_phone" value="{{ old('contact_phone', \App\Support\Phone::national($phone) ?: $phone) }}" autocomplete="tel" inputmode="tel" maxlength="30" placeholder="npr. 065 123 4567"
			@if ($errors->has('contact_phone')) aria-invalid="true" aria-describedby="pst-phone-error" @else aria-describedby="pst-phone-help" @endif>
		<p class="pst-help" id="pst-phone-help">
			@if ($accountPhone)
				Podrazumevano je broj sa vašeg naloga ({{ \App\Support\Phone::national($accountPhone) }}). Upišite drugi ako kupci treba da zovu na njega.
			@else
				Na nalogu nemate broj telefona. Bez broja kupci mogu da vam pišu samo porukom.
			@endif
			Broj se prikazuje tek kad kupac klikne na „Prikaži broj”.
		</p>
		@include('posting.partials.error', ['field' => 'contact_phone', 'id' => 'pst-phone'])
	</div>
	@include('posting.partials.actions', ['submitLabel' => 'Sačuvaj i pogledaj oglas'])
</form>
@endsection
