{{-- Hint for the profile owner about an empty field; visitors never see it. --}}
@if ($canEdit)
	<p class="lgp-hint">{{ \App\Support\Listing\Icons::profile('edit', 16) }}<span>{{ $text }} <a href="{{ $editUrl }}">Dopunite profil</a></span></p>
@endif
