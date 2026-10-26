{{-- Tabs of the firm self-service pages. $active: profile | stats | import --}}
<nav aria-label="Firma">
	<ul class="lgf-tabs">
		<li><a href="{{ url_slash('moj-nalog/firma') }}" @if ($active === 'profile') aria-current="page" @endif>Profil firme</a></li>
		<li><a href="{{ url_slash('moj-nalog/firma/statistika') }}" @if ($active === 'stats') aria-current="page" @endif>Statistika</a></li>
		<li><a href="{{ url_slash('moj-nalog/firma/uvoz-oglasa') }}" @if ($active === 'import') aria-current="page" @endif>Uvoz oglasa</a></li>
		<li><a href="{{ url_slash('korisnik/'.$user->slug) }}">Javni profil</a></li>
		<li><a href="{{ url_slash('moj-nalog') }}">Moj nalog</a></li>
	</ul>
</nav>
