{{--
	Account menu: a sidebar beside every account page on a computer; on the phone the overview page
	shows it as a list and the other pages get a "back to the account" link instead.
	$acctMenuList: render as the phone list on the overview page.
--}}
@php
	$acctUser = auth()->user();
	$acctGroups = \App\Support\Nalozi\AccountMenu::groups($acctUser);
	$acctActive = \App\Support\Nalozi\AccountMenu::active($acctUser, request()->path());
	$acctList = $acctMenuList ?? false;
@endphp
<nav class="lg-acct-nav{{ $acctList ? ' lg-acct-nav--list' : '' }}" aria-label="{{ $acctList ? 'Moj nalog, meni' : 'Moj nalog' }}">
	@unless ($acctList)
		<a class="lg-acct-nav__me" href="{{ url_slash('moj-nalog/podesavanja') }}">
			<span class="lg-acct-nav__avatar" aria-hidden="true">
				@if ($acctUser->avatarUrl())
					<img src="{{ $acctUser->avatarUrl() }}" alt="" width="44" height="44">
				@else
					{{ $acctUser->initials() }}
				@endif
			</span>
			<span class="lg-acct-nav__who">
				<strong>{{ $acctUser->firm?->name ?: $acctUser->name }}</strong>
				<small>{{ $acctUser->isFirm() ? 'Firma' : 'Privatno lice' }}</small>
			</span>
		</a>
	@endunless
	@foreach ($acctGroups as $group)
		<div class="lg-acct-nav__group">
			@if ($group['title'])
				<p class="lg-acct-nav__title">{{ $group['title'] }}</p>
			@endif
			<ul>
				@foreach ($group['items'] as $item)
					@continue($acctList && $item['path'] === 'moj-nalog')
					<li>
						<a href="{{ url_slash($item['path']) }}"{!! ! $acctList && $item['path'] === $acctActive ? ' aria-current="page"' : '' !!}>
							{{ \App\Support\Stranice\Ikonice::svg($item['icon'], 20) }}
							<span class="lg-acct-nav__label">{{ $item['label'] }}</span>
							@if ($item['count'] !== null)
								<span class="lg-acct-nav__count{{ $item['alert'] ? ' is-alert' : '' }}">{{ $item['count'] }}@if ($item['alert'])<span class="lg-visually-hidden"> novo</span>@endif</span>
							@endif
							@if ($acctList)
								{{ \App\Support\Stranice\Ikonice::svg('chevron-r', 18) }}
							@endif
						</a>
					</li>
				@endforeach
			</ul>
		</div>
	@endforeach
	<div class="lg-acct-nav__group lg-acct-nav__group--end">
		<ul>
			<li><a href="{{ $acctUser->profileUrl() }}">{{ \App\Support\Stranice\Ikonice::svg('eye', 20) }}<span class="lg-acct-nav__label">Moj javni profil</span></a></li>
			<li>
				<form method="post" action="{{ url_slash('odjava') }}">
					@csrf
					<button type="submit">{{ \App\Support\Stranice\Ikonice::svg('logout', 20) }}<span class="lg-acct-nav__label">Odjavi se</span></button>
				</form>
			</li>
		</ul>
	</div>
</nav>
