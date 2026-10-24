{{-- One message bubble. $message, $mine --}}
@use('App\Services\Messages\MessageBody')
<li class="lgm-msg{{ $mine ? ' lgm-msg--mine' : '' }}" id="poruka-{{ $message->id }}" data-id="{{ $message->id }}" data-sent="{{ $message->created_at->toIso8601String() }}">
	<div class="lgm-msg__bubble">
		<span class="lg-visually-hidden">{{ $mine ? 'Vi:' : 'Sagovornik:' }}</span>
		<p class="lgm-msg__body">{{ MessageBody::html($message->body) }}</p>
	</div>
	<time class="lgm-msg__time" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->isToday() ? $message->created_at->format('H:i') : $message->created_at->format('j. n. Y. H:i') }}</time>
</li>
