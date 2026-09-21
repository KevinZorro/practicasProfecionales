{{--
    Editar y mover unidades entre estados funcionales (RF66). Qué
    transiciones se ofrecen sale del enum y de cuántas unidades hay en cada
    estado, así que la pantalla no repite el flujo; qué botones aparecen lo
    decide la Policy.
--}}
@php($compacto = $compacto ?? false)
@php($variante = $compacto ? 'tabla' : 'movil')

<div @class(['flex flex-wrap gap-2', 'mt-3 border-t border-gray-200 pt-3' => ! $compacto])>
    @can('update', $item)
        <x-boton variante="secundario" href="{{ route('panel.inventario.editar', $item) }}" class="px-3 py-2">
            Editar
        </x-boton>
    @endcan

    @foreach (\App\Enums\EstadoItemInventario::conContador() as $origen)
        @continue($item->cantidadEn($origen) === 0)

        @foreach ($origen->siguientes() as $destino)
            @can($destino->esDefinitivo() ? 'darDeBaja' : 'cambiarEstadoFuncional', $item)
                <x-boton variante="secundario" type="button"
                         wire:click="pedirCambioDeEstado({{ $item->id }}, '{{ $origen->value }}', '{{ $destino->value }}')"
                         class="px-3 py-2">
                    @if ($destino->esDefinitivo())
                        Dar de baja {{ mb_strtolower($origen->etiqueta()) }}
                    @else
                        {{ mb_strtolower($origen->etiqueta()) }} → {{ mb_strtolower($destino->etiqueta()) }}
                    @endif
                </x-boton>
            @endcan
        @endforeach
    @endforeach
</div>

@if ($cambiandoEstado === $item->id)
    @php($origen = \App\Enums\EstadoItemInventario::from($estadoOrigen))
    @php($destino = \App\Enums\EstadoItemInventario::from($estadoDestino))
    <div class="mt-3 space-y-3 rounded-md bg-amber-50 p-3 ring-1 ring-inset ring-amber-600/20" role="alertdialog">
        <p class="text-sm font-medium text-amber-900">
            Mover unidades de «{{ $item->nombre }}» de «{{ $origen->etiqueta() }}» a «{{ $destino->etiqueta() }}»
        </p>
        <p class="text-sm text-amber-900">
            Hay {{ $item->cantidadEn($origen) }} en «{{ mb_strtolower($origen->etiqueta()) }}».
        </p>

        @if ($destino->esDefinitivo())
            <p class="text-sm text-amber-900">
                La baja es definitiva: esas unidades salen del total y no vuelven. El ítem
                no se borra, porque el histórico de solicitudes lo referencia.
            </p>
        @endif

        <div>
            <label for="cantidad-{{ $variante }}-{{ $item->id }}" class="mb-1 block text-sm font-medium text-amber-900">
                Cuántas unidades
            </label>
            <input type="number" min="1" max="{{ $item->cantidadEn($origen) }}" inputmode="numeric"
                   wire:model="cantidad" id="cantidad-{{ $variante }}-{{ $item->id }}"
                   class="w-28 rounded-md border border-amber-300 px-3 py-2 text-base focus:border-amber-600 focus:ring-amber-600">
            @error('cantidad') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="motivo-{{ $variante }}-{{ $item->id }}" class="mb-1 block text-sm font-medium text-amber-900">
                Motivo
            </label>
            <textarea wire:model="motivo" id="motivo-{{ $variante }}-{{ $item->id }}" rows="2"
                      placeholder="Qué les pasa: «a dos sondas no les infla el balón»."
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
            Historial de unidades ({{ $item->cambiosDeEstado->count() }})
        </summary>
        <ul class="mt-2 space-y-2 border-l-2 border-gray-200 pl-3">
            @foreach ($item->cambiosDeEstado as $cambio)
                {{-- El parcial se pinta dos veces —tarjetas en móvil, tabla en
                     escritorio—, así que la clave lleva la variante: dentro de
                     un componente Livewire las claves no pueden repetirse. --}}
                <li wire:key="cambio-{{ $variante }}-{{ $cambio->id }}" class="text-sm">
                    <p class="text-gray-900">
                        {{ $cambio->cantidad }} ×
                        {{ $cambio->estado_anterior?->etiqueta() ?? 'Entrada' }} → {{ $cambio->estado_nuevo->etiqueta() }}
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
