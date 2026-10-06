<x-filament-widgets::widget>
    {{-- Inline styles only: the admin CSS bundle is prebuilt and has no classes for new utilities. --}}
    <div x-data="{ copied: false }"
         style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 0.75rem; padding: 0.5rem 0.75rem; font-size: 0.8125rem; border: 1px dashed rgba(127,127,127,.35); border-radius: 0.5rem;">
        <span style="font-weight: 600; opacity: .75;">Product feed URL</span>
        <span style="opacity: .55;">Google Merchant / Facebook Catalog</span>
        <input type="text" readonly onclick="this.select()" value="{{ $this->getFeedUrl() }}"
               style="flex: 1 1 14rem; min-width: 0; padding: 0.25rem 0.5rem; font-family: monospace; font-size: 0.75rem; border: 1px solid rgba(127,127,127,.3); border-radius: 0.375rem; background: transparent;">
        <x-filament::button size="xs" color="gray" type="button"
            x-on:click="navigator.clipboard.writeText(@js($this->getFeedUrl())); copied = true; setTimeout(() => copied = false, 1500)">
            <span x-show="!copied">Copy</span>
            <span x-show="copied" x-cloak>Copied!</span>
        </x-filament::button>
    </div>
</x-filament-widgets::widget>
