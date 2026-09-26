<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Product feed URL</x-slot>
        <x-slot name="description">Google Merchant / Facebook Catalog — active products only.</x-slot>

        <div class="flex flex-col sm:flex-row gap-2" x-data="{ copied: false }">
            <input
                type="text"
                readonly
                onclick="this.select()"
                value="{{ $this->getFeedUrl() }}"
                class="fi-input flex-1 rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 text-sm font-mono px-3 py-2"
            >
            <button
                type="button"
                x-on:click="
                    navigator.clipboard.writeText('{{ $this->getFeedUrl() }}');
                    copied = true;
                    setTimeout(() => copied = false, 1500);
                "
                class="fi-btn fi-btn-size-md inline-flex items-center justify-center gap-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500"
            >
                <span x-show="!copied">Copy</span>
                <span x-show="copied" x-cloak>Copied!</span>
            </button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
