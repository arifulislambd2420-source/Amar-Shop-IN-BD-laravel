<x-filament-widgets::widget>
    <div class="dash-details">
        <button type="button" wire:click="toggle" class="dash-details-head">
            <span>{{ __('বিস্তারিত রিপোর্ট') }}</span>
            <small>{{ __('লাভ-লোকসান, পণ্য, কাস্টমার, পেমেন্ট, স্টক') }}</small>
            <x-filament::icon :icon="$open ? 'heroicon-m-chevron-up' : 'heroicon-m-chevron-down'" class="h-5 w-5" />
        </button>

        @if ($open)
            <div class="dash-tabs" role="tablist">
                @foreach ($tabs as $key => $label)
                    <button type="button" role="tab" wire:click="setTab('{{ $key }}')"
                        class="dash-tab {{ $tab === $key ? 'is-active' : '' }}">{{ __($label) }}</button>
                @endforeach
            </div>

            <div class="dash-tab-body">
                @if ($widget) @livewire($widget, ['pageFilters' => $this->pageFilters], key('dash-detail-'.$tab)) @endif
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
