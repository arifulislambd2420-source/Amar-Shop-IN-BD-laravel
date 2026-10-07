<x-filament-panels::page>
    <form wire:submit="upload">
        {{ $this->form }}

        <div style="margin-top: 1rem;">
            <x-filament::button type="submit">
                Upload
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
            <p style="font-size: 0.875rem; opacity: 0.6;">No images uploaded yet.</p>
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
                                    onclick="navigator.clipboard.writeText('{{ url($file['url']) }}'); new FilamentNotification().title('Copied!').success().send();"
                                >
                                    Copy
                                </x-filament::button>

                                <x-filament::button
                                    type="button"
                                    color="danger"
                                    size="xs"
                                    style="flex: 1 1 0%;"
                                    wire:click="deleteFile('{{ $file['key'] }}')"
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
