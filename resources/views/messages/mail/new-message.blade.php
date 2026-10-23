{{-- Plain text (text/plain): values are printed raw, there is no HTML to escape. --}}
@use('App\Services\Messages\MessageBody')
Zdravo, {!! $recipient->name !!}!

@if ($unread > 1)
Imate {{ $unread }} nove poruke od korisnika {!! $sender->name !!}@if ($listingTitle) za oglas „{!! $listingTitle !!}”@endif.
@else
Imate novu poruku od korisnika {!! $sender->name !!}@if ($listingTitle) za oglas „{!! $listingTitle !!}”@endif.
@endif

„{!! MessageBody::excerpt($latest->body, 300) !!}”

Pročitajte i odgovorite: {!! $url !!}

Ne šaljite novac unapred i ne plaćajte preko linkova koje dobijete porukom.

--
loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.
Obaveštenja o porukama možete da isključite u aplikaciji ili na stranici Moj nalog: {!! $settingsUrl !!}
