{{--
    Editar y cambiar el estado funcional (RF66). Qué transiciones se ofrecen
    sale del enum, así que la pantalla no repite el flujo; qué botones
    aparecen lo decide la Policy. La baja explica que el registro no se borra.
--}}
@php($compacto = $compacto ?? false)

<div @class(['flex flex-wrap gap-2', 'mt-3 border-t border-gray-200 pt-3' => ! $compacto])>
    @can('update', $item)
        <x-boton variante="secundario" href="{{ route('panel.inventario.editar', $item) }}" class="px-3 py-2">
            Editar
        </x-boton>
    @endcan

    @foreach ($item->estado->siguientes() as $destino)
        @can($destino->esDefinitivo() ? 'darDeBaja' : 'cambiarEstadoFuncional', $item)
            <x-boton variante="secundario" type="button"
                     wire:click="pedirCambioDeEstado({{ $item->id }}, '{{ $destino->value }}')"
                     class="px-3 py-2">
                {{ $destino->esDefinitivo() ? 'Dar de baja' : 'Pasar a '.mb_strtolower($destino->etiqueta()) }}
            </x-boton>
        @endcan
    @endforeach
</div>

@if ($cambiandoEstado === $item->id)
    @php($destino = \App\Enums\EstadoItemInventario::from($estadoDestino))
    <div class="mt-3 space-y-3 rounded-md bg-amber-50 p-3 ring-1 ring-inset ring-amber-600/20" role="alertdialog">
        <p class="text-sm font-medium text-amber-900">
            «{{ $item->nombre }}» pasa a «{{ $destino->etiqueta() }}»
        </p>

        @if ($destino->esDefinitivo())
            <p class="text-sm text-amber-900">
                La baja es definitiva. El registro no se borra —el histórico de solicitudes lo
                referencia y perderlo dejaría esas solicitudes sin sentido—, pero el ítem sale
                del catálogo y ya no vuelve a cambiar de estado.
            </p>
        @endif

        <div>
            <label for="motivo-{{ $item->id }}" class="mb-1 block text-sm font-medium text-amber-900">
                Motivo
            </label>
            <textarea wire:model="motivo" id="motivo-{{ $item->id }}" rows="2"
                      placeholder="Qué le pasa a la pieza: «el balón de la sonda no infla»."
                      class="w-full rounded-md border border-amber-300 px-3 py-2 text-base focus:border-amber-600 focus:ring-amber-600"></textarea>
            @error('motivo') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
        </div>

        @if ($errorDeRegla)
            <p class="text-sm text-rose-800" role="alert">{{ $errorDeRegla }}</p>
        @endif

        <div class="flex flex-wrap gap-2">
            <x-boton type="button" wire:click="confirmarCambioDeEstado" wire:loading.attr="disabled" class="px-4 py-2">
                Confirmar
            </x-boton>
            <x-boton variante="secundario" type="button" wire:click="cancelarCambioDeEstado" class="px-4 py-2">
                Cancelar
            </x-boton>
        </div>
    </div>
@endif

@if ($item->cambiosDeEstado->isNotEmpty())
    <details class="mt-3">
        <summary class="cursor-pointer text-sm text-sky-800">
            Historial de estado ({{ $item->cambiosDeEstado->count() }})
        </summary>
        <ul class="mt-2 space-y-2 border-l-2 border-gray-200 pl-3">
            @foreach ($item->cambiosDeEstado as $cambio)
                {{-- El parcial se pinta dos veces —tarjetas en móvil, tabla en
                     escritorio—, así que la clave lleva la variante: dentro de
                     un componente Livewire las claves no pueden repetirse. --}}
                <li wire:key="cambio-{{ $compacto ? 'tabla' : 'movil' }}-{{ $cambio->id }}" class="text-sm">
                    <p class="text-gray-900">
                        {{ $cambio->estado_anterior?->etiqueta() ?? 'Alta' }} → {{ $cambio->estado_nuevo->etiqueta() }}
                    </p>
                    <p class="text-gray-600">{{ $cambio->motivo }}</p>
                    <p class="text-xs text-gray-500">
                        {{ $cambio->registradoPor?->nombre ?? 'Sin registrar' }} ·
                        {{ $cambio->created_at->format('d/m/Y H:i') }}
                    </p>
                </li>
            @endforeach
        </ul>
    </details>
@endif
