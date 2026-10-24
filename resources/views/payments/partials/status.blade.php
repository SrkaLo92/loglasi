{{-- Success message and the general order error above the content. --}}
@if (session('status'))
	<p class="lgpay-alert lgpay-alert--ok" role="status">{{ session('status') }}</p>
@endif
@if ($errors->any())
	<div class="lgpay-alert lgpay-alert--error" role="alert">
		@foreach ($errors->all() as $message)
			<p>{{ $message }}</p>
		@endforeach
	</div>
@endif
