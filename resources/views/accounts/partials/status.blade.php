{{-- Success message and general error above the form. --}}
@if (session('status'))
	<p class="lgn-alert lgn-alert--ok" role="status">{{ session('status') }}</p>
@endif
