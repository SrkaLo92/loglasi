{{-- Navigation between Messages and Favourites. --}}
<nav class="lgm-tabs" aria-label="Moj nalog">
	<a href="{{ url_slash('moj-nalog') }}">Moj nalog</a>
	<a href="{{ url_slash('moj-nalog/poruke') }}" @if ($active === 'messages') aria-current="page" @endif>Poruke</a>
	<a href="{{ url_slash('moj-nalog/omiljeni') }}" @if ($active === 'favorites') aria-current="page" @endif>Omiljeni oglasi</a>
</nav>
