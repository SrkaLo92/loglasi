{{-- Error for one field: @include('posting.partials.error', ['field' => 'title', 'id' => 'pst-title']) --}}
@if ($errors->has($field))
	<p class="pst-error" id="{{ $id }}-error">{{ $errors->first($field) }}</p>
@endif
