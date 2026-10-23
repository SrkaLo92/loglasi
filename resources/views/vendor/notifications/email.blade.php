{{-- Serbian version of the framework notification mail; every MailMessage uses it. --}}
<x-mail::message :unsubscribe="$unsubscribeUrl ?? null">
@if (! empty($greeting))
# {{ $greeting }}
@else
# Zdravo!
@endif

@foreach ($introLines as $line)
{{ $line }}

@endforeach

@isset($actionText)
<x-mail::button :url="$actionUrl" :color="$level === 'error' ? 'error' : 'primary'">
{{ $actionText }}
</x-mail::button>
@endisset

@foreach ($outroLines as $line)
{{ $line }}

@endforeach

@if (! empty($salutation))
{{ $salutation }}
@else
Pozdrav,<br>
loglasi.rs
@endif

@isset($actionText)
<x-slot:subcopy>
Ako dugme „{{ $actionText }}” ne radi, kopirajte ovaj link u pregledač: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
