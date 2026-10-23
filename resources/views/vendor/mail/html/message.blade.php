{{-- Branded layout of every loglasi.rs mail. $unsubscribe: one-click unsubscribe URL of the mail's category (null for account mail). --}}
@props(['unsubscribe' => null])
<x-mail::layout>
<x-slot:header>
<x-mail::header :url="url_slash('/')">
loglasi.rs
</x-mail::header>
</x-slot:header>

{!! $slot !!}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

<x-slot:footer>
<x-mail::footer>
<span class="tagline">loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.</span>

[Podešavanja obaveštenja]({{ url_slash('moj-nalog/obavestenja') }})@if ($unsubscribe) · [Odjavite se od ovih poruka]({{ $unsubscribe }})@endif
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
