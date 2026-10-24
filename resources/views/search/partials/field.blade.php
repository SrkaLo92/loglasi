{{-- One field filter of the category (ListingFields::filters()): exact = select, multi = pills, range = from/to. --}}
@php
	$fid = 'sr-f-'.$field['key'];
@endphp
@if ($field['filter'] === 'range')
	<fieldset class="sr-group">
		<legend class="sr-group__title">{{ $field['label'] }}{{ $field['unit'] ? ' ('.$field['unit'].')' : '' }}</legend>
		<div class="sr-range sr-range--two">
			<label>Od<input class="sr-input" type="text" inputmode="numeric" name="f[{{ $field['key'] }}_od]" value="{{ $filters->fieldValue($field['key'].'_od') }}" autocomplete="off"></label>
			<label>Do<input class="sr-input" type="text" inputmode="numeric" name="f[{{ $field['key'] }}_do]" value="{{ $filters->fieldValue($field['key'].'_do') }}" autocomplete="off"></label>
		</div>
	</fieldset>
@elseif ($field['type'] === 'checkbox')
	<div class="sr-group sr-group--tight">
		<label class="sr-check"><input type="checkbox" name="f[{{ $field['key'] }}]" value="1" @checked($filters->fieldValue($field['key']) === '1')>{{ $field['label'] }}</label>
	</div>
@elseif ($field['filter'] === 'multi')
	<fieldset class="sr-group">
		<legend class="sr-group__title">{{ $field['label'] }}</legend>
		<div class="sr-pills">
			@foreach ($field['options'] as $option)
				<label class="sr-pill"><input type="checkbox" name="f[{{ $field['key'] }}][]" value="{{ $option['value'] }}" @checked(in_array($option['value'], $filters->fieldValues($field['key']), true))><span>{{ $option['label'] }}</span></label>
			@endforeach
		</div>
	</fieldset>
@else
	<div class="sr-group">
		<label class="sr-group__title" for="{{ $fid }}">{{ $field['label'] }}</label>
		<select class="sr-input" id="{{ $fid }}" name="f[{{ $field['key'] }}]">
			<option value="">Sve</option>
			@foreach ($field['options'] as $option)
				<option value="{{ $option['value'] }}" @selected($filters->fieldValue($field['key']) === $option['value'])>{{ $option['label'] }}</option>
			@endforeach
		</select>
	</div>
@endif
