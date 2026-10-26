@extends('layouts.app')
@include('messages.partials.head')
@use('App\Services\Messages\MessageBody')
@use('App\Support\Pages\Pricing')
@php
	$title = 'Poruke | loglasi.rs';
	$robots = 'noindex, follow';
@endphp

@section('content')
<div class="lgm lg-wrap">
	<h1>Poruke</h1>
	@include('messages.partials.status')

	@if ($unreadTotal > 0)
		<p class="lgm-lead">Imate {{ $unreadTotal }} {{ Pricing::plural($unreadTotal, 'nepročitanu poruku', 'nepročitane poruke', 'nepročitanih poruka') }}.</p>
	@endif

	@if ($groups->isEmpty())
		<div class="lgm-empty">
			@if ($isFirstPage)
				<p>Još nemate poruka. Kada pišete prodavcu ili vam neko pošalje poruku o oglasu, razgovor će biti ovde.</p>
				<a class="lg-btn lg-btn--green" href="{{ url_slash('oglasi') }}">Pogledajte oglase</a>
			@else
				<p>Nema starijih razgovora.</p>
				<a class="lg-btn lg-btn--outline" href="{{ url_slash('moj-nalog/poruke') }}">Najnoviji razgovori</a>
			@endif
		</div>
	@else
		<div class="lgm-inbox">
			@foreach ($groups as $group)
				<section class="lgm-group" aria-label="{{ $group['listing'] && ! $group['listing']->trashed() ? 'Oglas: '.$group['listing']->title : 'Obrisan oglas' }}">
					@include('messages.partials.listing', ['listing' => $group['listing'], 'thumb' => $group['thumb']])
					<ul class="lgm-convs">
						@foreach ($group['conversations'] as $conversation)
							@php
								$other = $conversation->otherUser($user);
								$unread = $conversation->unreadFor($user);
								$last = $conversation->lastMessage;
								$otherName = $other && ! $other->trashed() ? $other->name : 'Obrisan korisnik';
							@endphp
							<li>
								<a class="lgm-conv{{ $unread > 0 ? ' lgm-conv--unread' : '' }}" href="{{ url_slash('moj-nalog/poruke/'.$conversation->id) }}">
									<span class="lgm-conv__who">
										{{ $otherName }}
										@if ($other?->isFirm())<span class="lgm-badge lgm-badge--firm">Firma</span>@endif
										<span class="lgm-conv__role">{{ $conversation->isBuyer($user) ? 'prodavac' : 'kupac' }}</span>
									</span>
									<span class="lgm-conv__last">
										@if ($last)
											@if ((int) $last->sender_id === $user->id)<span class="lgm-conv__you">Vi:</span>@endif
											{{ MessageBody::excerpt($last->body) }}
										@endif
									</span>
									<span class="lgm-conv__meta">
										@if ($conversation->last_message_at)
											<time datetime="{{ $conversation->last_message_at->toIso8601String() }}">{{ $conversation->last_message_at->isToday() ? $conversation->last_message_at->format('H:i') : $conversation->last_message_at->format('j. n. Y.') }}</time>
										@endif
										@if ($unread > 0)
											<span class="lgm-count" aria-label="{{ $unread }} {{ Pricing::plural($unread, 'nepročitana', 'nepročitane', 'nepročitanih') }}">{{ $unread > 99 ? '99+' : $unread }}</span>
										@endif
									</span>
								</a>
							</li>
						@endforeach
					</ul>
				</section>
			@endforeach
		</div>

		<nav class="lgm-pager" aria-label="Stranice razgovora">
			@unless ($isFirstPage)
				<a class="lg-btn lg-btn--outline" href="{{ url_slash('moj-nalog/poruke') }}">Najnoviji razgovori</a>
			@endunless
			@if ($next)
				<a class="lg-btn lg-btn--outline" href="{{ url_slash('moj-nalog/poruke') }}?cursor={{ urlencode($next) }}" rel="next">Stariji razgovori</a>
			@endif
		</nav>
	@endif
</div>
@endsection
