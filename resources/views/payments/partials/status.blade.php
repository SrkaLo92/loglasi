{{-- Success message and the general order error above the content. --}}
@if (session('status'))
	<p class="lgp-alert lgp-alert--ok" role="status">{{ session('status') }}</p>
@endif
@if ($errors->any())
	<div class="lgp-alert lgp-alert--error" role="alert">
		@foreach ($errors->all() as $message)
			<p>{{ $message }}</p>
		@endforeach
	</div>
@endif
