@extends('posting.frame')
@use('App\Enums\FieldType')

@section('step')
<form class="pst-card pst-form" method="post" action="{{ \App\Support\Posting\Wizard::url($listing, 'details') }}" novalidate>
	@csrf
	@method('PUT')
	<h2>Detalji</h2>
	<p class="pst-help">Polja označena zvezdicom su obavezna. Ostala pomažu kupcima da nađu oglas u pretrazi.</p>

	@foreach ($categoryFields as $field)
		@php
			$name = 'fields['.$field->key.']';
			$id = 'pst-f-'.$field->key;
			$errKey = 'fields.'.$field->key;
			$value = old('fields.'.$field->key, $values[$field->key] ?? null);
			$options = collect((array) $field->options)->map(fn ($o) => is_array($o) ? ['value' => (string) ($o['value'] ?? ''), 'label' => (string) ($o['label'] ?? $o['value'] ?? '')] : ['value' => (string) $o, 'label' => (string) $o]);
			$invalid = $errors->has($errKey) || $errors->has($errKey.'.*');
			$label = $field->label.($field->required ? ' *' : '');
		@endphp

		@if ($field->type === FieldType::Multiselect)
			<fieldset class="pst-field" @if ($invalid) aria-describedby="{{ $id }}-error" @endif>
				<legend>{{ $label }}</legend>
				<div class="pst-checks">
					@foreach ($options as $option)
						<label class="pst-check"><input type="checkbox" name="{{ $name }}[]" value="{{ $option['value'] }}" @checked(in_array($option['value'], (array) $value, true))> {{ $option['label'] }}</label>
					@endforeach
				</div>
				@if ($invalid)<p class="pst-error" id="{{ $id }}-error">{{ $errors->first($errKey) ?: $errors->first($errKey.'.*') }}</p>@endif
			</fieldset>
		@elseif ($field->type === FieldType::Checkbox)
			<div class="pst-field">
				<input type="hidden" name="{{ $name }}" value="0">
				<label class="pst-check"><input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" @checked(filter_var($value, FILTER_VALIDATE_BOOLEAN))> {{ $label }}</label>
				@include('posting.partials.error', ['field' => $errKey, 'id' => $id])
			</div>
		@else
			<div class="pst-field">
				<label for="{{ $id }}">{{ $label }}@if ($field->unit) <span class="pst-unit">({{ $field->unit }})</span>@endif</label>
				@if ($field->type === FieldType::Select)
					<select id="{{ $id }}" name="{{ $name }}" @if ($field->required) required @endif @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
						<option value="">Izaberite</option>
						@foreach ($options as $option)
							<option value="{{ $option['value'] }}" @selected((string) $value === $option['value'])>{{ $option['label'] }}</option>
						@endforeach
					</select>
				@else
					<input id="{{ $id }}" type="text" name="{{ $name }}" value="{{ is_array($value) ? '' : $value }}"
						@if (in_array($field->type, [FieldType::Number, FieldType::Year], true)) inputmode="numeric" @endif
						@if ($field->type === FieldType::Year) maxlength="4" placeholder="npr. {{ date('Y') - 10 }}" @else maxlength="255" @endif
						@if ($field->required) required @endif @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
				@endif
				@include('posting.partials.error', ['field' => $errKey, 'id' => $id])
			</div>
		@endif
	@endforeach

	@include('posting.partials.actions')
</form>
@endsection
