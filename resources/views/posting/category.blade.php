@extends('posting.frame')

@section('step')
<section class="pst-card" aria-labelledby="pst-cat-title">
	<h2 id="pst-cat-title">Kategorija</h2>
	<p class="pst-help">Sada: <strong>{{ $breadcrumbs->pluck('name')->implode(' › ') }}</strong>. Izaberite drugu ako oglas ne pripada ovde. Vrednosti dodatnih polja koje nova kategorija nema neće biti sačuvane.</p>
	@include('posting.partials.picker', ['action' => $action, 'baseUrl' => $action])
</section>
<div class="pst-actions">
	<a class="pst-btn" href="{{ \App\Support\Posting\Wizard::url($listing, 'basics') }}">Nastavi bez promene</a>
</div>
@endsection
