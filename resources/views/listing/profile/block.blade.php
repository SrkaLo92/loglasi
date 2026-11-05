{{-- Block / unblock the profile's owner (signed-in visitors only). --}}
@auth
	@unless ($isOwner)
		<div class="lgp-block" id="blokiranje">
			@if (session('status'))
				<p class="lgp-block__status" role="status">{{ session('status') }}</p>
			@endif
			<form method="post" action="{{ url_slash('moj-nalog/blokirani/'.$user->id) }}">
				@csrf
				@if ($iBlocked)
					@method('DELETE')
					<span>Blokirali ste ovog korisnika.</span>
					<button class="lgp-block__btn" type="submit">Odblokiraj korisnika</button>
				@else
					<button class="lgp-block__btn" type="submit">Blokiraj korisnika</button>
				@endif
			</form>
		</div>
	@endunless
@endauth
