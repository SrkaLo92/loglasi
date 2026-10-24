<x-filament-widgets::widget>
    <x-filament::section heading="Čeka na vas">
        <div class="lg-queue">
            @foreach ($items as $item)
                <a href="{{ $item['url'] }}">
                    <span @class(['lg-queue-count', 'is-hot' => $item['count'] > 0])>{{ number_format($item['count'], 0, ',', '.') }}</span>
                    <span class="lg-queue-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
