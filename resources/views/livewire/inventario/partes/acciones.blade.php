{{--
    Editar y mover unidades entre estados funcionales (RF66). Qué
    transiciones se ofrecen sale del enum y de cuántas unidades hay en cada
    estado, así que la pantalla no repite el flujo; qué botones aparecen lo
    decide la Policy.
--}}
@php($compacto = $compacto ?? false)
@php($variante = $compacto ? 'tabla' : 'movil')

<div @class(['flex flex-wrap gap-2', 'mt-3 border-t border-gray-100 pt-3' => ! $compacto])>
    @can('update', $item)
        <x-boton tamano="chico" variante="secundario" href="{{ route('panel.inventario.editar', $item) }}">
            Editar
        </x-boton>
    @endcan

    @foreach (\App\Enums\EstadoItemInventario::conContador() as $origen)
        @continue($item->cantidadEn($origen) === 0)

        @foreach ($origen->siguientes() as $destino)
            @can($destino->esDefinitivo() ? 'darDeBaja' : 'cambiarEstadoFuncional', $item)
                <x-boton :variante="$destino->esDefinitivo() ? 'peligro-suave' : 'secundario'" tamano="chico" type="button"
                         wire:click="pedirCambioDeEstado({{ $item->id }}, '{{ $origen->value }}', '{{ $destino->value }}')">
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
    <div class="mt-3 space-y-4 rounded-xl bg-gray-50 p-3.5" role="alertdialog" aria-labelledby="mover-{{ $variante }}-{{ $item->id }}">
        <div>
            <p class="text-[15px] font-semibold text-gray-900" id="mover-{{ $variante }}-{{ $item->id }}">
                Mover unidades de «{{ $item->nombre }}» de «{{ $origen->etiqueta() }}» a «{{ $destino->etiqueta() }}»
            </p>
            <p class="mt-0.5 text-[14px] text-gray-600">
                Hay {{ $item->cantidadEn($origen) }} en «{{ mb_strtolower($origen->etiqueta()) }}».
            </p>
        </div>

        @if ($destino->esDefinitivo())
            <x-aviso tono="peligro">
                La baja es definitiva: esas unidades salen del total y no vuelven. El ítem
                no se borra, porque el histórico de solicitudes lo referencia.
            </x-aviso>
        @endif

        <div>
            <label for="cantidad-{{ $variante }}-{{ $item->id }}" class="campo-etiqueta">Cuántas unidades</label>
            <input type="number" min="1" max="{{ $item->cantidadEn($origen) }}" inputmode="numeric"
                   wire:model="cantidad" id="cantidad-{{ $variante }}-{{ $item->id }}" class="campo w-28">
            @error('cantidad') <p class="campo-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="motivo-{{ $variante }}-{{ $item->id }}" class="campo-etiqueta">Motivo</label>
            <textarea wire:model="motivo" id="motivo-{{ $variante }}-{{ $item->id }}" rows="2" class="campo"
                      placeholder="Qué les pasa: «a dos sondas no les infla el balón»."></textarea>
            @error('motivo') <p class="campo-error">{{ $message }}</p> @enderror
        </div>

        @if ($errorDeRegla)
            <x-aviso tono="peligro" role="alert">{{ $errorDeRegla }}</x-aviso>
        @endif

        <div class="flex flex-wrap gap-2">
            <x-boton :variante="$destino->esDefinitivo() ? 'peligro' : 'primario'" tamano="chico" type="button" wire:click="confirmarCambioDeEstado" wire:loading.attr="disabled">
                {{ $destino->esDefinitivo() ? 'Dar de baja' : 'Mover las unidades' }}
            </x-boton>
            <x-boton variante="secundario" tamano="chico" type="button" wire:click="cancelarCambioDeEstado">
                Cancelar
            </x-boton>
        </div>
    </div>
@endif

@if ($item->cambiosDeEstado->isNotEmpty())
    <details class="mt-3">
        <summary class="inline-flex cursor-pointer select-none items-center gap-1 rounded-full text-[14px] font-semibold text-gray-700 hover:text-gray-900 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rojo">
            Historial de unidades ({{ $item->cambiosDeEstado->count() }})
        </summary>
        <ul class="mt-2 divide-y divide-gray-100 rounded-xl bg-gray-50 px-3.5">
            @foreach ($item->cambiosDeEstado as $cambio)
                {{-- El parcial se pinta dos veces —tarjetas en móvil, tabla en
                     escritorio—, así que la clave lleva la variante: dentro de
                     un componente Livewire las claves no pueden repetirse. --}}
                <li wire:key="cambio-{{ $variante }}-{{ $cambio->id }}" class="py-2.5 text-[14px] leading-snug">
                    <p class="text-gray-900">
                        {{ $cambio->cantidad }} ×
                        {{ $cambio->estado_anterior?->etiqueta() ?? 'Entrada' }} → {{ $cambio->estado_nuevo->etiqueta() }}
                    </p>
                    <p class="text-gray-600">{{ $cambio->motivo }}</p>
                    <p class="text-[13px] text-gray-500">
                        {{ $cambio->registradoPor?->nombre ?? 'Sin registrar' }} ·
                        {{ $cambio->created_at->format('d/m/Y H:i') }}
                    </p>
                </li>
            @endforeach
        </ul>
    </details>
@endif
