{{-- One navigation for every account page, like the account menu on big classified sites; it scrolls sideways on a phone. --}}
@php
	$lgAcctTabs = [
		'moj-nalog' => 'Pregled',
		'moj-nalog/oglasi' => 'Moji oglasi',
		'moj-nalog/poruke' => 'Poruke',
		'moj-nalog/omiljeni' => 'Omiljeni',
		'moj-nalog/pretrage' => 'Pretrage',
		'moj-nalog/obavestenja' => 'Obaveštenja',
		'moj-nalog/narudzbine' => 'Narudžbine',
	];
	if (auth()->user()->isFirm()) {
		$lgAcctTabs['moj-nalog/firma'] = 'Firma';
	}
	$lgAcctPath = trim(request()->path(), '/');
	$lgAcctActive = collect(array_keys($lgAcctTabs))
		->filter(fn ($path) => $lgAcctPath === $path || str_starts_with($lgAcctPath, $path.'/'))
		->sortByDesc(fn ($path) => strlen($path))
		->first();
@endphp
<nav class="lg-acct lg-wrap" aria-label="Moj nalog">
	<ul class="lg-acct__list">
		@foreach ($lgAcctTabs as $path => $label)
			<li><a href="{{ url_slash($path) }}"{!! $path === $lgAcctActive ? ' aria-current="page"' : '' !!}>{{ $label }}</a></li>
		@endforeach
	</ul>
</nav>
