{{-- Greška za jedno polje: @include('nalozi.partials.greska', ['field' => 'email', 'bag' => 'default']) --}}
@php($__bag = $errors->getBag($bag ?? 'default'))
@if ($__bag->has($field))
	<p class="lgn-error" id="{{ ($prefix ?? '').$field }}-greska">{{ $__bag->first($field) }}</p>
@endif
