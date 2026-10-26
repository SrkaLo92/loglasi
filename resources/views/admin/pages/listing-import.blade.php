<x-filament-panels::page>

    <x-filament::section heading="Kako izgleda tabela" collapsible collapsed>
        <p class="text-sm">Prvi red su nazivi kolona. Jedan red je jedan oglas.</p>
        <p class="text-sm mt-2"><strong>Obavezno:</strong> {{ implode(', ', $help['required']) }}.
            <strong>Po potrebi:</strong> {{ implode(', ', $help['optional']) }}.</p>
        <p class="text-sm mt-2">Kolona <strong>slike</strong> prima adrese slika razdvojene razmakom ili zarezom. Prazna cena znači „Dogovor”.</p>
        <p class="text-sm mt-2"><strong>Dodatna polja po kategoriji:</strong> {{ implode(', ', array_slice($help['fields'], 0, 40)) }}.</p>
    </x-filament::section>

    @if ($preview)
        <x-filament::section heading="Pregled pre uvoza">
            @if ($preview['unknown'])
                <p class="text-sm text-warning-600 dark:text-warning-400">Ove kolone ne prepoznajemo i preskačemo ih: {{ implode(', ', $preview['unknown']) }}.</p>
            @endif
            <table class="w-full text-sm mt-3">
                <thead><tr class="text-left"><th class="py-1">Red</th><th>Naslov</th><th>Kategorija</th><th>Status</th></tr></thead>
                <tbody>
                @foreach ($preview['rows'] as $row)
                    <tr class="border-t border-gray-200 dark:border-white/10 align-top">
                        <td class="py-1">{{ $row['line'] }}</td>
                        <td>{{ $row['title'] ?: '—' }}</td>
                        <td>{{ $row['category'] ?? '—' }}</td>
                        <td>
                            @if ($row['errors'])
                                <span class="text-danger-600 dark:text-danger-400">{{ implode(' ', $row['errors']) }}</span>
                            @else
                                <span class="text-success-600 dark:text-success-400">Spremno</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif

    @if ($report)
        <x-filament::section heading="Izveštaj">
            <p class="text-sm">Uvezeno: <strong>{{ $report['created'] }}</strong>, preskočeno: <strong>{{ $report['failed'] }}</strong>.</p>
            @if ($report['failed'] > 0)
                <table class="w-full text-sm mt-3">
                    <thead><tr class="text-left"><th class="py-1">Red</th><th>Naslov</th><th>Greška</th></tr></thead>
                    <tbody>
                    @foreach ($report['rows'] as $row)
                        @if ($row['errors'])
                            <tr class="border-t border-gray-200 dark:border-white/10 align-top">
                                <td class="py-1">{{ $row['line'] }}</td>
                                <td>{{ $row['title'] ?: '—' }}</td>
                                <td class="text-danger-600 dark:text-danger-400">{{ implode(' ', $row['errors']) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
