{{-- Solo componentes de Filament: su CSS viene compilado y no incluye clases sueltas de Tailwind. --}}
<x-filament-panels::page>
    <x-filament-panels::form wire:submit="guardar">
        {{ $this->form }}

        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>
</x-filament-panels::page>
