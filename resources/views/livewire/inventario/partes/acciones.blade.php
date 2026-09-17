{{--
    Editar y dar de baja. Qué aparece lo decide la Policy; la confirmación
    de baja explica que el registro no se borra.
--}}
@php($compacto = $compacto ?? false)

<div @class(['flex flex-wrap gap-2', 'mt-3 border-t border-gray-200 pt-3' => ! $compacto])>
    @can('update', $item)
        <x-boton variante="secundario" href="{{ route('panel.inventario.editar', $item) }}" class="px-3 py-2">
            Editar
        </x-boton>
    @endcan

    @can('darDeBaja', $item)
        @if ($item->estado !== \App\Enums\EstadoItemInventario::Baja)
            <x-boton variante="secundario" type="button" wire:click="pedirConfirmacionDeBaja({{ $item->id }})" class="px-3 py-2">
                Dar de baja
            </x-boton>
        @endif
    @endcan
</div>

@if ($bajaPendiente === $item->id)
    <div class="mt-3 space-y-2 rounded-md bg-amber-50 p-3 ring-1 ring-inset ring-amber-600/20" role="alertdialog">
        <p class="text-sm font-medium text-amber-900">¿Dar de baja «{{ $item->nombre }}»?</p>
        <p class="text-sm text-amber-900">
            El registro no se borra: el histórico de solicitudes lo referencia y perderlo
            dejaría esas solicitudes sin sentido. Solo deja de contar como disponible.
        </p>
        <div class="flex flex-wrap gap-2">
            <x-boton type="button" wire:click="darDeBaja({{ $item->id }})" class="px-4 py-2">Sí, dar de baja</x-boton>
            <x-boton variante="secundario" type="button" wire:click="cancelarBaja" class="px-4 py-2">Cancelar</x-boton>
        </div>
    </div>
@endif
