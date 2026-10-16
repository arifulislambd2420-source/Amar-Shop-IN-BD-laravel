<x-filament-panels::page>
    <form wire:submit.prevent>
        {{ $this->form }}

        <div style="margin-top: 1.5rem;">
            {{ $this->saveAction }}
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
