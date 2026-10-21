<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('অর্ডারের অবস্থা') }}</x-slot>
        <x-slot name="description">{{ $label }} · {{ number_format($total) }} {{ __('অর্ডার') }}</x-slot>

        @if ($total === 0)
            <div class="dash-empty">{{ __('এই সময়ে কোনো অর্ডার নেই।') }}</div>
        @else
            <ul class="dash-status-list">
                @foreach ($rows as $row)
                    <li>
                        <a href="{{ $row['url'] }}" class="dash-status-row {{ $row['count'] ? '' : 'is-zero' }}">
                            <span class="dash-status-name">{{ $row['label'] }}</span>
                            <span class="dash-status-bar"><span style="width: {{ $row['pct'] }}%"></span></span>
                            <span class="dash-status-count">{{ number_format($row['count']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
