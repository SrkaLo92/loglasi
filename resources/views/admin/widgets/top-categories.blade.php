<x-filament-widgets::widget>
    <x-filament::section heading="Najjače kategorije" description="Glavne kategorije po broju aktivnih oglasa">
        @if ($rows === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">Još nema aktivnih oglasa.</p>
        @else
            <ul class="lg-bars">
                @foreach ($rows as $row)
                    <li>
                        <span class="lg-name" title="{{ $row['name'] }}">{{ $row['name'] }}</span>
                        <span class="lg-bar" aria-hidden="true"><span style="width: {{ max(2, (int) round(100 * $row['count'] / $max)) }}%"></span></span>
                        <span class="lg-num">{{ number_format($row['count'], 0, ',', '.') }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
