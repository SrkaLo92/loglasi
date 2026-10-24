@php
    $n = fn (int $value) => number_format($value, 0, ',', '.');
    $platforms = ['android' => 'Android', 'ios' => 'iOS', 'web' => 'Veb'];
@endphp
<x-filament-panels::page>
    <div class="lg-stats-grid">
        <x-filament::section heading="Sačuvane pretrage">
            <dl class="lg-dl">
                <div><dt>Ukupno</dt><dd>{{ $n($stats['searches']['total']) }}</dd></div>
                <div><dt>Sa obaveštenjem e-poštom</dt><dd>{{ $n($stats['searches']['notify']) }}</dd></div>
                <div><dt>Korisnika sa pretragom</dt><dd>{{ $n($stats['searches']['users']) }}</dd></div>
                <div><dt>Novih za 7 dana</dt><dd>{{ $n($stats['searches']['week']) }}</dd></div>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Novi oglasi za pretrage" description="Poslednjih 30 dana">
            <dl class="lg-dl">
                <div><dt>Pronađeno</dt><dd>{{ $n($stats['matches']['month']) }}</dd></div>
                <div><dt>Od toga za 7 dana</dt><dd>{{ $n($stats['matches']['week']) }}</dd></div>
                <div><dt>Poslato u obaveštenju</dt><dd>{{ $n($stats['matches']['sent']) }}</dd></div>
                <div><dt>Čeka sledeće slanje</dt><dd>{{ $n($stats['matches']['waiting']) }}</dd></div>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Aplikacija (push)">
            <dl class="lg-dl">
                <div><dt>Uređaja</dt><dd>{{ $n($stats['devices']['total']) }}</dd></div>
                <div><dt>Korisnika</dt><dd>{{ $n($stats['devices']['users']) }}</dd></div>
                <div><dt>Aktivnih za 30 dana</dt><dd>{{ $n($stats['devices']['active']) }}</dd></div>
                @foreach ($stats['platforms'] as $platform => $count)
                    <div><dt>{{ $platforms[$platform] ?? $platform }}</dt><dd>{{ $n($count) }}</dd></div>
                @endforeach
            </dl>
        </x-filament::section>
    </div>

    <x-filament::section heading="Najčešće sačuvane pretrage">
        @if ($stats['top'] === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">Još nema sačuvanih pretraga.</p>
        @else
            <ol class="lg-top-list">
                @foreach ($stats['top'] as $row)
                    <li><span>{{ $row['name'] !== '' ? $row['name'] : 'Bez naziva' }}</span><span class="lg-num">{{ $n($row['count']) }}</span></li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-panels::page>
