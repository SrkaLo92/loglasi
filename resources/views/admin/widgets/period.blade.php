<x-filament-widgets::widget>
    <x-filament::section heading="Statistika">
        <div class="lg-period-wrap">
            <table class="lg-period">
                <thead>
                    <tr>
                        <th scope="col"><span class="sr-only">Pokazatelj</span></th>
                        @foreach ($periods as $label)
                            <th scope="col">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $key => $label)
                        <tr>
                            <th scope="row">{{ $label }}</th>
                            @foreach (array_keys($periods) as $period)
                                <td>{{ number_format($totals[$period][$key], 0, ',', '.') }}{{ $key === 'revenue' ? "\u{00A0}RSD" : '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
