<x-filament-panels::page>
    <form wire:submit="upload">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Upload
            </x-filament::button>
        </div>
    </form>

    <div class="mt-8">
        @php($files = $this->getFiles())

        @if ($files->isEmpty())
            <p class="fi-fo-field-wrp-helper-text text-sm text-gray-500 dark:text-gray-400">
                No images uploaded yet.
            </p>
        @else
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                @foreach ($files as $file)
                    <div
                        wire:key="media-{{ $file['basename'] }}"
                        class="rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 overflow-hidden flex flex-col"
                    >
                        <img
                            src="{{ $file['url'] }}"
                            alt="{{ $file['basename'] }}"
                            loading="lazy"
                            class="w-full aspect-square object-cover"
                        >

                        <div class="p-2 flex flex-col gap-2">
                            <input
                                type="text"
                                readonly
                                value="{{ $file['url'] }}"
                                onclick="this.select()"
                                class="w-full text-[11px] rounded border-gray-300 dark:border-white/10 dark:bg-gray-900 dark:text-gray-300 px-1.5 py-1"
                            >

                            <div class="flex gap-1.5">
                                <x-filament::button
                                    type="button"
                                    color="gray"
                                    size="xs"
                                    class="flex-1"
                                    onclick="navigator.clipboard.writeText(@js($file['url'])); new FilamentNotification().title('Copied!').success().send();"
                                >
                                    Copy
                                </x-filament::button>

                                <x-filament::button
                                    type="button"
                                    color="danger"
                                    size="xs"
                                    class="flex-1"
                                    wire:click="deleteFile(@js($file['basename']))"
                                    wire:confirm="Delete this image permanently? This cannot be undone."
                                >
                                    Delete
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
