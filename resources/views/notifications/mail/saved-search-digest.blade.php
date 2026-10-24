@php
    $plain = fn ($t) => \App\Notifications\Messages\BrandedMailMessage::plain((string) $t);
@endphp
<x-mail::message :unsubscribe="$unsubscribeUrl ?? null">
# Novi oglasi za vaše pretrage

Od poslednjeg obaveštenja stiglo je {{ \App\Notifications\SavedSearchDigestNotification::newListings($total) }}.

@foreach ($searches as $search)
## {{ $plain($search['name']) }}

@foreach ($search['listings'] as $listing)
- [{{ $plain($listing['title']) }}]({{ $listing['url'] }}): **{{ $listing['price'] }}**@if ($listing['location']), {{ $plain($listing['location']) }}@endif

@endforeach
@if ($search['total'] > count($search['listings']))
[Pogledajte sve oglase ({{ $search['total'] }})]({{ $search['url'] }})
@else
[Otvorite pretragu]({{ $search['url'] }})
@endif

@endforeach
<x-mail::button :url="$manageUrl">
Moje sačuvane pretrage
</x-mail::button>

Pozdrav,<br>
loglasi.rs

<x-slot:subcopy>
Za svaku pretragu šaljemo najviše jedan mejl dnevno. Obaveštenja za pojedinačnu pretragu isključujete na stranici „Sačuvane pretrage”.
</x-slot:subcopy>
</x-mail::message>
