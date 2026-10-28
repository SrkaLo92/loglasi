@php
    $n = fn (int $value) => number_format($value, 0, ',', '.');
    $ago = fn (?int $at) => $at ? \Illuminate\Support\Carbon::createFromTimestamp($at)->diffForHumans() : 'nikad';
@endphp
<x-filament-panels::page>
    <div class="lg-stats-grid">
        <x-filament::section heading="Cron (zakazani poslovi)">
            <dl class="lg-dl">
                <div><dt>Stanje</dt><dd class="{{ $scheduler['ok'] ? 'lg-ok' : 'lg-bad' }}">{{ $scheduler['ok'] ? 'Radi' : 'Ne radi' }}</dd></div>
                <div><dt>Poslednje pokretanje</dt><dd>{{ $ago($scheduler['at']) }}</dd></div>
            </dl>
            @unless ($scheduler['ok'])
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">Cron treba da se pokreće svakog minuta. Bez njega ne idu mejlovi, push ni istek oglasa.</p>
            @endunless
        </x-filament::section>

        <x-filament::section heading="Red za slanje (mejlovi, push)">
            <dl class="lg-dl">
                <div><dt>Čeka slanje</dt><dd>{{ $n($queue['waiting']) }}</dd></div>
                <div><dt>Zakazano za kasnije</dt><dd>{{ $n($queue['delayed']) }}</dd></div>
                <div><dt>Najstariji čeka od</dt><dd>{{ $queue['oldest'] ? $ago($queue['oldest']) : '—' }}</dd></div>
                <div><dt>Neuspelo</dt><dd class="{{ $queue['failed'] > 0 ? 'lg-bad' : '' }}">{{ $n($queue['failed']) }}</dd></div>
            </dl>
        </x-filament::section>

        <x-filament::section heading="Podešavanja">
            <dl class="lg-dl">
                @foreach ($settings as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </dl>
        </x-filament::section>
    </div>

    <x-filament::section heading="Neuspeli poslovi" description="Posao koji ni posle 3 pokušaja nije prošao (npr. mejl koji server nije primio).">
        @if ($failed === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">Nema neuspelih poslova.</p>
        @else
            <div class="lg-period-wrap">
                <table class="lg-period">
                    <thead><tr><th>Posao</th><th>Kada</th><th>Greška</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($failed as $job)
                            <tr wire:key="failed-{{ $job['uuid'] }}">
                                <td>{{ $job['job'] }}</td>
                                <td>{{ $job['at'] }}</td>
                                <td class="lg-msg">{{ $job['error'] }}</td>
                                <td>
                                    <div class="lg-row-actions">
                                        <x-filament::button size="xs" wire:click="retryJob('{{ $job['uuid'] }}')">Pokušaj ponovo</x-filament::button>
                                        <x-filament::button size="xs" color="gray" wire:click="forgetJob('{{ $job['uuid'] }}')" wire:confirm="Obrisati ovaj neuspeli posao?">Obriši</x-filament::button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Poslednje greške na sajtu" description="Iz dnevnika greške, najnovije prve.">
        @if ($logErrors === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">Nema zabeleženih grešaka.</p>
        @else
            <div class="lg-period-wrap">
                <table class="lg-period">
                    <thead><tr><th>Kada</th><th>Vrsta</th><th>Poruka</th></tr></thead>
                    <tbody>
                        @foreach ($logErrors as $row)
                            <tr>
                                <td>{{ $row['at'] }}</td>
                                <td class="{{ $row['level'] === 'WARNING' ? '' : 'lg-bad' }}">{{ $row['level'] === 'WARNING' ? 'Upozorenje' : 'Greška' }}</td>
                                <td class="lg-msg">{{ $row['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    <x-filament::section heading="Masovni mejlovi">
        @if ($announcements === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">Još nije poslat nijedan.</p>
        @else
            <dl class="lg-dl">
                @foreach ($announcements as $row)
                    <div><dt>{{ $row['campaign'] }} <span class="text-gray-400">({{ $row['last'] }})</span></dt><dd>{{ $n($row['sent']) }} poslato</dd></div>
                @endforeach
            </dl>
        @endif
    </x-filament::section>
</x-filament-panels::page>
