@extends('posting.frame')
@use('App\Enums\FieldType')

@section('step')
<form class="pst-card pst-form" method="post" action="{{ \App\Support\Posting\Wizard::url($listing, 'details') }}" novalidate>
	@csrf
	@method('PUT')
	<h2>Detalji</h2>
	<p class="pst-help">Polja označena zvezdicom su obavezna. Ostala pomažu kupcima da nađu oglas u pretrazi.</p>

	@php $fieldsByKey = $categoryFields->keyBy('key'); @endphp
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
					@if ($field->depends_on && $fieldsByKey->has($field->depends_on))
						{{-- Model depends on make: without JavaScript every model is listed, grouped by make. --}}
						@php
							$parent = $fieldsByKey->get($field->depends_on);
							$parentLabels = collect((array) $parent->options)->pluck('label', 'value')->all();
							$parentValue = (string) old('fields.'.$parent->key, $values[$parent->key] ?? '');
							$dependent = collect((array) $field->options)->filter(fn ($o) => is_array($o))
								->when($parentValue !== '', fn ($c) => $c->filter(fn ($o) => (string) ($o['parent'] ?? '') === $parentValue));
							$groups = $parentValue !== '' ? ['' => $dependent->all()] : $dependent->groupBy(fn ($o) => $parentLabels[$o['parent'] ?? ''] ?? '')->all();
						@endphp
						<select id="{{ $id }}" name="{{ $name }}" data-field-key="{{ $field->key }}" data-depends-on="{{ $parent->key }}" data-needs-parent data-placeholder="Izaberite" data-waiting="Prvo izaberite: {{ mb_strtolower($parent->label) }}" @if ($field->required) required @endif @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
							<option value="">Izaberite</option>
							@foreach ($groups as $group => $groupOptions)
								@if ($group !== '')<optgroup label="{{ $group }}">@endif
								@foreach ($groupOptions as $option)
									<option value="{{ $option['value'] }}" data-parent="{{ $option['parent'] ?? '' }}" @selected((string) $value === (string) $option['value'])>{{ $option['label'] }}</option>
								@endforeach
								@if ($group !== '')</optgroup>@endif
							@endforeach
						</select>
					@else
						<select id="{{ $id }}" name="{{ $name }}" data-field-key="{{ $field->key }}" @if ($field->required) required @endif @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
							<option value="">Izaberite</option>
							@foreach ($options as $option)
								<option value="{{ $option['value'] }}" @selected((string) $value === $option['value'])>{{ $option['label'] }}</option>
							@endforeach
						</select>
					@endif
				@else
					<input id="{{ $id }}" type="text" name="{{ $name }}" value="{{ is_array($value) ? '' : $value }}"
						@if (in_array($field->type, [FieldType::Number, FieldType::Year], true)) inputmode="numeric" @endif
						@if ($field->type === FieldType::Year) maxlength="4" placeholder="npr. {{ date('Y') - 10 }}" @elseif ($field->type === FieldType::Number) maxlength="15" @else maxlength="255" @endif
						@if ($field->required) required @endif @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
				@endif
				@include('posting.partials.error', ['field' => $errKey, 'id' => $id])
			</div>
		@endif
	@endforeach

	@include('posting.partials.actions')
</form>
@endsection
