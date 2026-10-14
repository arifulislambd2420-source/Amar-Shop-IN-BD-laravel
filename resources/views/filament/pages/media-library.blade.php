<x-filament-panels::page>
    <form wire:submit="upload">
        {{ $this->form }}

        <div style="margin-top: 1rem;">
            <x-filament::button type="submit">
                {{ __('আপলোড করুন') }}
            </x-filament::button>
        </div>
    </form>

    {{--
        Plain inline styles on purpose, not Tailwind utility classes: this
        view is rendered inside the Filament admin panel, whose CSS is a
        separate, pre-built bundle (vendor/filament/*/dist/*.css) that only
        contains classes Filament's own package source uses — it does not
        get regenerated from this app's blade files, and the production
        deploy pipeline never runs `npm run build` here either (see the
        commit that fixed the Vite-manifest 500 error). A Tailwind class
        invented in this file would silently have no CSS at all. Inline
        styles and Filament's own <x-filament::button> (its real compiled
        component) are the only things guaranteed to render correctly.
    --}}
    <div style="margin-top: 2rem;">
        @php($files = $this->getFiles())

        @if ($files->isEmpty())
            <p style="font-size: 0.875rem; opacity: 0.6;">{{ __('এখনও কোনো ছবি আপলোড হয়নি।') }}</p>
        @else
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem;">
                @foreach ($files as $file)
                    <div
                        wire:key="media-{{ $file['key'] }}"
                        style="border: 1px solid rgba(127, 127, 127, 0.25); border-radius: 0.5rem; overflow: hidden; display: flex; flex-direction: column;"
                    >
                        <img
                            src="{{ $file['url'] }}"
                            alt="{{ $file['name'] }}"
                            loading="lazy"
                            style="width: 100%; aspect-ratio: 1 / 1; object-fit: cover; display: block;"
                        >

                        <div style="padding: 0.5rem; display: flex; flex-direction: column; gap: 0.5rem;">
                            <input
                                type="text"
                                readonly
                                value="{{ url($file['url']) }}"
                                onclick="this.select()"
                                style="width: 100%; box-sizing: border-box; font-size: 11px; padding: 0.25rem 0.375rem; border: 1px solid rgba(127, 127, 127, 0.3); border-radius: 0.25rem; background: transparent;"
                            >

                            <div style="display: flex; gap: 0.375rem;">
                                {{--
                                    Plain '{{ $value }}' interpolation, not @js() — a
                                    <x-component> tag's plain (non ":"-prefixed) attributes
                                    are compiled as literal strings, so a custom @directive
                                    call inside one is never expanded; it would ship to the
                                    browser as the literal text "@js(...)" (confirmed via
                                    Blade::compileString() while debugging this exact bug).
                                    {{ }} echoes DO get compiled correctly in this position.
                                    Safe without Js::from()'s extra quoting here because both
                                    values are server-generated (a site URL and a UUID-based
                                    filename) and can never contain a quote char.
                                --}}
                                <x-filament::button
                                    type="button"
                                    color="gray"
                                    size="xs"
                                    style="flex: 1 1 0%;"
                                    onclick="navigator.clipboard.writeText('{{ url($file['url']) }}'); new FilamentNotification().title('{{ __('কপি হয়েছে!') }}').success().send();"
                                >
                                    {{ __('কপি') }}
                                </x-filament::button>

                                <x-filament::button
                                    type="button"
                                    color="danger"
                                    size="xs"
                                    style="flex: 1 1 0%;"
                                    wire:click="askDelete('{{ $file['key'] }}')"
                                >
                                    {{ __('মুছুন') }}
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    {{-- Delete dialog: says where the picture is used before it is deleted. --}}
    @if ($pending)
        <div style="position: fixed; inset: 0; z-index: 60; display: flex; align-items: center; justify-content: center; padding: 1rem; background: rgba(0, 0, 0, 0.55);"
            wire:key="delete-dialog" wire:keydown.escape="cancelDelete">
            <div role="dialog" aria-modal="true" aria-labelledby="delete-dialog-title"
                style="width: 100%; max-width: 28rem; max-height: 90vh; overflow-y: auto; border-radius: 0.75rem; padding: 1.25rem; background: var(--fi-color-white, #fff); color: #111827; box-shadow: 0 20px 50px rgba(0, 0, 0, 0.35);">
                <h2 id="delete-dialog-title" style="font-size: 1.125rem; font-weight: 700; margin-bottom: 0.75rem;">
                    {{ $pending['places'] ? __('এই ছবিটি ব্যবহার হচ্ছে — তবু মুছবেন?') : __('ছবিটি চিরতরে মুছবেন?') }}
                </h2>

                <img src="{{ \App\Support\Media::urlFor(config('media.directory').'/'.$pending['key']) }}" alt="" style="width: 6rem; height: 6rem; object-fit: cover; border-radius: 0.5rem; margin-bottom: 0.75rem;">

                @if ($pending['places'])
                    <p style="font-size: 0.875rem; margin-bottom: 0.5rem;">{{ __('ছবিটি এই জায়গাগুলোতে ব্যবহার হচ্ছে:') }}</p>
                    <ul style="font-size: 0.875rem; margin: 0 0 0.75rem 1.25rem; list-style: disc;">
                        @foreach ($pending['places'] as $place)
                            <li>{{ $place }}</li>
                        @endforeach
                    </ul>
                    <p style="font-size: 0.875rem; padding: 0.625rem 0.75rem; border-radius: 0.5rem; background: #fef3c7; color: #78350f; margin-bottom: 1rem;">
                        {{ __('মুছলে এই জায়গাগুলোর ছবির ঘর ফাঁকা হয়ে যাবে (কোনো পেজ ভাঙবে না)। পরে নতুন ছবি দিয়ে ভরে নিতে পারবেন। এটা আর ফেরানো যাবে না।') }}
                    </p>
                @else
                    <p style="font-size: 0.875rem; margin-bottom: 1rem;">{{ __('ছবিটি কোথাও ব্যবহার হচ্ছে না। মুছলে আর ফেরানো যাবে না।') }}</p>
                @endif

                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <x-filament::button type="button" color="gray" wire:click="cancelDelete">{{ __('বাতিল করুন') }}</x-filament::button>
                    <x-filament::button type="button" color="danger" wire:click="confirmDelete">
                        {{ $pending['places'] ? __('তবু মুছুন') : __('মুছুন') }}
                    </x-filament::button>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
