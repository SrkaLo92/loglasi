{{-- One listing field of the details step. Expects $field, $fieldsByKey, $values. --}}
@use('App\Enums\FieldType')
@use('App\Services\ListingFields')
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
	@php
		$chosen = array_map('strval', (array) $value);
		$visible = $options->take(12);
		$rest = $options->slice(12);
	@endphp
	<fieldset class="pst-field pst-chips" @if ($invalid) aria-describedby="{{ $id }}-error" @endif>
		<legend>{{ $label }}@if ($chosen !== []) <span class="pst-chips__count">izabrano: {{ count($chosen) }}</span>@endif</legend>
		<div class="pst-checks">
			@foreach ($visible as $option)
				<label class="pst-check pst-check--chip"><input type="checkbox" name="{{ $name }}[]" value="{{ $option['value'] }}" @checked(in_array($option['value'], $chosen, true))> {{ $option['label'] }}</label>
			@endforeach
		</div>
		@if ($rest->isNotEmpty())
			<details class="pst-more" @if ($rest->contains(fn ($o) => in_array($o['value'], $chosen, true))) open @endif>
				<summary>Prikaži još {{ $rest->count() }}</summary>
				<div class="pst-checks">
					@foreach ($rest as $option)
						<label class="pst-check pst-check--chip"><input type="checkbox" name="{{ $name }}[]" value="{{ $option['value'] }}" @checked(in_array($option['value'], $chosen, true))> {{ $option['label'] }}</label>
					@endforeach
				</div>
			</details>
		@endif
		@if ($invalid)<p class="pst-error" id="{{ $id }}-error">{{ $errors->first($errKey) ?: $errors->first($errKey.'.*') }}</p>@endif
	</fieldset>
@elseif ($field->type === FieldType::Checkbox)
	<div class="pst-field">
		<input type="hidden" name="{{ $name }}" value="0">
		<label class="pst-check"><input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" @checked(filter_var($value, FILTER_VALIDATE_BOOLEAN))> {{ $label }}</label>
		@include('posting.partials.error', ['field' => $errKey, 'id' => $id])
	</div>
@else
	<div class="pst-field{{ $field->type === FieldType::Text ? ' pst-field--wide' : '' }}">
		<label for="{{ $id }}">{{ $label }}</label>
		@if ($field->type === FieldType::Select && $field->depends_on && $fieldsByKey->has($field->depends_on))
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
		@elseif ($field->type === FieldType::Select)
			<select id="{{ $id }}" name="{{ $name }}" data-field-key="{{ $field->key }}" @if ($field->required) required @endif @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
				<option value="">Izaberite</option>
				@foreach ($options as $option)
					<option value="{{ $option['value'] }}" @selected((string) $value === $option['value'])>{{ $option['label'] }}</option>
				@endforeach
			</select>
		@elseif ($field->type === FieldType::Year)
			{{-- A list of years like polovniautomobili; an odd stored value stays selectable. --}}
			@php
				$years = range((int) date('Y') + 1, ListingFields::YEAR_MIN);
				$current = is_scalar($value) ? trim((string) $value) : '';
				if ($current !== '' && ! in_array((int) $current, $years, true)) {
					array_unshift($years, $current);
				}
			@endphp
			<select id="{{ $id }}" name="{{ $name }}" data-field-key="{{ $field->key }}" @if ($field->required) required @endif @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
				<option value="">Izaberite godinu</option>
				@foreach ($years as $year)
					<option value="{{ $year }}" @selected($current !== '' && (string) $year === $current)>{{ $year }}.</option>
				@endforeach
			</select>
		@else
			<div class="pst-affix">
				<input id="{{ $id }}" type="text" name="{{ $name }}" value="{{ is_array($value) ? '' : $value }}"
					@if ($field->type === FieldType::Number) inputmode="decimal" maxlength="15" @else maxlength="255" @endif
					@if ($field->unit) class="has-unit" @endif
					@if ($field->required) required @endif @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif>
				@if ($field->unit)<span class="pst-affix__unit" aria-hidden="true">{{ $field->unit }}</span>@endif
			</div>
		@endif
		@include('posting.partials.error', ['field' => $errKey, 'id' => $id])
	</div>
@endif
