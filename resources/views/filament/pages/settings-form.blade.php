<x-filament-panels::page>
    {{-- Plain CSS on purpose: the admin's stylesheet is a prebuilt bundle, so Tailwind classes invented here would have no CSS. --}}
    <style>
        .settings-save-bar {
            position: sticky;
            bottom: 0;
            z-index: 10;
            margin-top: 1.5rem;
            padding: 0.75rem 1rem;
            border-top: 1px solid rgba(127, 127, 127, 0.25);
            background: color-mix(in srgb, #ffffff 94%, transparent);
            backdrop-filter: blur(6px);
        }
        .dark .settings-save-bar {
            background: color-mix(in srgb, #111827 94%, transparent);
        }
        .settings-save-bar .fi-btn {
            width: 100%;
        }
        @media (min-width: 640px) {
            .settings-save-bar {
                border: 1px solid rgba(127, 127, 127, 0.25);
                border-radius: 0.75rem;
            }
            .settings-save-bar .fi-btn {
                width: auto;
            }
        }
    </style>

    <form wire:submit="save">
        {{ $this->form }}

        {{-- Always reachable on a phone: the save button sticks to the bottom of the screen. --}}
        <div class="settings-save-bar">
            <x-filament::button type="submit" size="lg">
                সেভ করুন
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
