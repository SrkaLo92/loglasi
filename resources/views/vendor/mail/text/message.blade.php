{{-- Plain-text alternative of mail/html/message.blade.php. --}}
@props(['unsubscribe' => null])
<x-mail::layout>
<x-slot:header>
loglasi.rs
</x-slot:header>

{{ $slot }}

@isset($subcopy)
<x-slot:subcopy>
{{ $subcopy }}
</x-slot:subcopy>
@endisset

<x-slot:footer>
--
loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.
Podešavanja obaveštenja: {{ url_slash('moj-nalog/obavestenja/podesavanja') }}
@if ($unsubscribe)
Odjava od ovih poruka: {{ $unsubscribe }}
@endif
</x-slot:footer>
</x-mail::layout>
