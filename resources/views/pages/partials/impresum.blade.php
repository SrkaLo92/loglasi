{{-- Who runs the site (Zakon o elektronskoj trgovini, čl. 6), on Uslovi korišćenja and Politika privatnosti. --}}
@use('App\Support\Pages\Company')
<aside class="lgx-impresum" aria-label="{{ $impresumHeading }}">
	<h2 class="lgx-impresum__title">{{ $impresumHeading }}</h2>
	<dl class="lgx-impresum__list">
		<div><dt>Naziv</dt><dd>{{ Company::name() }}</dd></div>
		@if (Company::address() !== '')<div><dt>Sedište</dt><dd>{{ Company::address() }}</dd></div>@endif
		@if (Company::mb() !== '')<div><dt>Matični broj</dt><dd>{{ Company::mb() }}</dd></div>@endif
		@if (Company::pib() !== '')<div><dt>PIB</dt><dd>{{ Company::pib() }}</dd></div>@endif
		@if (Company::register() !== '')<div><dt>Registar</dt><dd>{{ Company::register() }}</dd></div>@endif
		@if (Company::email() !== '')<div><dt>E-pošta</dt><dd><a href="mailto:{{ Company::email() }}">{{ Company::email() }}</a></dd></div>@endif
		@if (Company::phone() !== '')<div><dt>Telefon</dt><dd>{{ Company::phone() }}</dd></div>@endif
	</dl>
</aside>
