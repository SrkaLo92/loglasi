{{-- One field filter of the category (ListingFields::filters()): exact = select, multi = pills, range = from/to. --}}
@php
	$fid = 'sr-f-'.$field['key'];
@endphp
@if ($field['filter'] === 'range')
	<fieldset class="sr-group">
		<legend class="sr-group__title">{{ $field['label'] }}{{ $field['unit'] ? ' ('.$field['unit'].')' : '' }}</legend>
		<div class="sr-range sr-range--two">
			@if ($field['type'] === 'year')
				@php $years = range((int) date('Y') + 1, \App\Services\ListingFields::YEAR_MIN); @endphp
				@foreach (['_od' => 'Od', '_do' => 'Do'] as $suffix => $word)
					@php $picked = (string) ($filters->fieldValue($field['key'].$suffix) ?? ''); @endphp
					<label>{{ $word }}<select class="sr-input" name="f[{{ $field['key'] }}{{ $suffix }}]">
						<option value="">Sve</option>
						@foreach ($years as $year)
							<option value="{{ $year }}" @selected($picked === (string) $year)>{{ $year }}.</option>
						@endforeach
					</select></label>
				@endforeach
			@else
			<label>Od<input class="sr-input" type="text" inputmode="numeric" name="f[{{ $field['key'] }}_od]" value="{{ $filters->fieldValue($field['key'].'_od') }}" autocomplete="off"></label>
			<label>Do<input class="sr-input" type="text" inputmode="numeric" name="f[{{ $field['key'] }}_do]" value="{{ $filters->fieldValue($field['key'].'_do') }}" autocomplete="off"></label>
			@endif
		</div>
	</fieldset>
@elseif ($field['type'] === 'checkbox')
	<div class="sr-group sr-group--tight">
		<label class="sr-check"><input type="checkbox" name="f[{{ $field['key'] }}]" value="1" @checked($filters->fieldValue($field['key']) === '1')>{{ $field['label'] }}</label>
	</div>
@elseif ($field['filter'] === 'multi')
	{{-- Long lists (car equipment) show the first options; the rest and the chosen ones stay reachable without JavaScript. --}}
	@php
		$chosen = $filters->fieldValues($field['key']);
		$all = collect($field['options']);
		$first = $all->take(12);
		$rest = $all->slice(12);
		$open = $rest->contains(fn ($o) => in_array($o['value'], $chosen, true));
	@endphp
	<fieldset class="sr-group">
		<legend class="sr-group__title">{{ $field['label'] }}</legend>
		<div class="sr-pills">
			@foreach ($first as $option)
				<label class="sr-pill"><input type="checkbox" data-field-key="{{ $field['key'] }}" name="f[{{ $field['key'] }}][]" value="{{ $option['value'] }}" @checked(in_array($option['value'], $chosen, true))><span>{{ $option['label'] }}</span></label>
			@endforeach
		</div>
		@if ($rest->isNotEmpty())
			<details class="sr-more" @if ($open) open @endif>
				<summary>Prikaži još ({{ $rest->count() }})</summary>
				<div class="sr-pills">
					@foreach ($rest as $option)
						<label class="sr-pill"><input type="checkbox" data-field-key="{{ $field['key'] }}" name="f[{{ $field['key'] }}][]" value="{{ $option['value'] }}" @checked(in_array($option['value'], $chosen, true))><span>{{ $option['label'] }}</span></label>
					@endforeach
				</div>
			</details>
		@endif
	</fieldset>
@elseif (! empty($field['depends_on']))
	{{-- Dependent select (model on make): only the chosen parent's options, otherwise all grouped by parent. --}}
	@php
		$parentField = collect($allFields ?? [])->firstWhere('key', $field['depends_on']);
		$parentLabels = collect($parentField['options'] ?? [])->pluck('label', 'value')->all();
		$chosenParents = $parentField ? $filters->fieldValues($parentField['key']) : [];
		$shown = collect($field['options'])->when($chosenParents !== [], fn ($c) => $c->filter(fn ($o) => in_array($o['parent'] ?? '', $chosenParents, true)));
		$groups = count($chosenParents) === 1 ? ['' => $shown->all()] : $shown->groupBy(fn ($o) => $parentLabels[$o['parent'] ?? ''] ?? '')->all();
	@endphp
	<div class="sr-group">
		<label class="sr-group__title" for="{{ $fid }}">{{ $field['label'] }}</label>
		<select class="sr-input" id="{{ $fid }}" name="f[{{ $field['key'] }}]" data-field-key="{{ $field['key'] }}" data-depends-on="{{ $field['depends_on'] }}" data-placeholder="Sve">
			<option value="">Sve</option>
			@foreach ($groups as $group => $options)
				@if ($group !== '')<optgroup label="{{ $group }}">@endif
				@foreach ($options as $option)
					<option value="{{ $option['value'] }}" data-parent="{{ $option['parent'] ?? '' }}" @selected($filters->fieldValue($field['key']) === $option['value'])>{{ $option['label'] }}</option>
				@endforeach
				@if ($group !== '')</optgroup>@endif
			@endforeach
		</select>
	</div>
@else
	<div class="sr-group">
		<label class="sr-group__title" for="{{ $fid }}">{{ $field['label'] }}</label>
		<select class="sr-input" id="{{ $fid }}" name="f[{{ $field['key'] }}]" data-field-key="{{ $field['key'] }}">
			<option value="">Sve</option>
			@foreach ($field['options'] as $option)
				<option value="{{ $option['value'] }}" @selected($filters->fieldValue($field['key']) === $option['value'])>{{ $option['label'] }}</option>
			@endforeach
		</select>
	</div>
@endif
