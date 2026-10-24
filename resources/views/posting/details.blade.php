@extends('posting.frame')
@use('App\Enums\FieldType')

@section('step')
<form class="pst-card pst-form" method="post" action="{{ \App\Support\Posting\Wizard::url($listing, 'details') }}" novalidate>
	@csrf
	@method('PUT')
	<h2>Detalji</h2>
	<p class="pst-help">Polja označena zvezdicom su obavezna. Ostala pomažu kupcima da nađu oglas u pretrazi.</p>

	@php
		$fieldsByKey = $categoryFields->keyBy('key');
		$basic = $categoryFields->reject(fn ($f) => in_array($f->type, [FieldType::Multiselect, FieldType::Checkbox], true));
		$flags = $categoryFields->where('type', FieldType::Checkbox);
		$lists = $categoryFields->where('type', FieldType::Multiselect);
	@endphp

	@if ($basic->isNotEmpty())
		<section class="pst-section">
			<h3 class="pst-section__title">Osnovni podaci</h3>
			<div class="pst-grid">
				@foreach ($basic as $field)
					@include('posting.partials.detail-field')
				@endforeach
			</div>
		</section>
	@endif

	@foreach ($lists as $field)
		<section class="pst-section">
			@include('posting.partials.detail-field')
		</section>
	@endforeach

	@if ($flags->isNotEmpty())
		<section class="pst-section">
			<h3 class="pst-section__title">Dodatno</h3>
			<div class="pst-checks">
				@foreach ($flags as $field)
					@include('posting.partials.detail-field')
				@endforeach
			</div>
		</section>
	@endif

	@include('posting.partials.actions')
</form>
@endsection
