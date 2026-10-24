{{-- Link to the existing listing after a duplicate was found. --}}
@if (session('duplicate'))
	@php($dup = session('duplicate'))
	<div class="pst-alert pst-alert--warn" role="alert">
		<p>Već imate sličan oglas: <a href="{{ $dup['url'] }}">„{{ $dup['title'] }}”</a>.</p>
		@if ($dup['renew'])
			<p><a class="pst-link-strong" href="{{ $dup['renew'] }}">Obnovite postojeći oglas besplatno</a></p>
		@endif
	</div>
@endif
