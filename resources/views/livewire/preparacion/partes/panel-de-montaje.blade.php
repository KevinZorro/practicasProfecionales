{{--
    Panel de montaje de un escenario. Va dentro de la tarjeta del tablero,
    no en una pantalla aparte: quien monta necesita ver la hora y el caso
    clínico mientras marca el material.
--}}
<div class="mt-4 space-y-6 border-t border-gray-100 pt-5">

    @if ($conflicto)
        <x-aviso tono="peligro" role="alert">
            {{ $conflicto }}
        </x-aviso>
    @endif

    {{-- Sala --}}
    <div>
        <h3 class="mb-2 text-[15px] font-semibold text-gray-900">Sala</h3>

        @if ($salasLibres->isEmpty())
            <x-aviso tono="atencion">
                No queda ninguna sala libre en esta franja.
            </x-aviso>
        @else
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-0 flex-1 basis-56">
                    <label for="sala-{{ $montaje->id }}" class="sr-only">Sala</label>
                    <select wire:model="salaElegida" id="sala-{{ $montaje->id }}"
                            class="campo">
                        <option value="">Elige una sala libre</option>
                        @foreach ($salasLibres as $sala)
                            <option value="{{ $sala->id }}">{{ $sala->nombreCompleto() }}{{ $sala->del_escenario ? ' · recomendada para este escenario' : '' }}</option>
                        @endforeach
                    </select>
                </div>
                <x-boton type="button" wire:click="asignarSala({{ $montaje->id }})">
                    {{ $montaje->sala ? 'Cambiar' : 'Asignar' }}
                </x-boton>
            </div>
            @error('salaElegida') <p class="campo-error">{{ $message }}</p> @enderror
        @endif
    </div>

    {{-- Alistamiento --}}
    <div>
        <h3 class="mb-1 text-[15px] font-semibold text-gray-900">Material</h3>

        {{-- Lo que monta el ingeniero va aparte y primero: hay que llamarlo antes (RF72). --}}
        @php($grupos = $montaje->items->groupBy(fn ($item) => in_array($item->id, $delIngeniero, true) ? 'ingeniero' : $item->tipo->value)->sortKeysUsing(fn ($a, $b) => ($b === 'ingeniero') <=> ($a === 'ingeniero')))

        @forelse ($grupos as $tipo => $delTipo)
            <h4 @class(['mb-2 mt-4 text-[13px] font-semibold', 'text-violet-800' => $tipo === 'ingeniero', 'text-gray-500' => $tipo !== 'ingeniero'])>
                {{ $tipo === 'ingeniero' ? 'Requiere al ingeniero (alta fidelidad)' : \App\Enums\TipoItemInventario::from($tipo)->etiqueta() }}
            </h4>
            <ul class="space-y-2">
                @foreach ($delTipo as $item)
                    <li wire:key="item-{{ $montaje->id }}-{{ $item->id }}">
                        {{-- La etiqueta entera es el objetivo táctil: se marca
                             con el pulgar, no con la punta del dedo. --}}
                        <label class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl bg-gray-50 px-3.5 py-2.5 transition-colors duration-150 hover:bg-gray-100 has-[:checked]:bg-emerald-50">
                            <input type="checkbox"
                                   wire:click="alternarItem({{ $montaje->id }}, {{ $item->id }})"
                                   @checked($item->pivot->alistado)
                                   class="casilla size-6">
                            <span class="min-w-0 flex-1 text-[16px] leading-snug text-gray-900">{{ $item->nombre }}</span>
                            <span class="shrink-0 text-[16px] font-semibold text-gray-700">×{{ $item->pivot->cantidad }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        @empty
            <p class="text-[14px] text-gray-500">Este escenario no lleva equipos.</p>
        @endforelse
    </div>

    {{-- Observaciones --}}
    <div>
        <label for="observaciones-{{ $montaje->id }}" class="mb-2 block text-[15px] font-semibold text-gray-900">
            Observaciones del alistamiento
        </label>
        <textarea wire:model="observaciones" id="observaciones-{{ $montaje->id }}" rows="2"
                  class="campo"
                  placeholder="Algo que el docente deba saber al llegar."></textarea>
        @error('observaciones') <p class="campo-error">{{ $message }}</p> @enderror
        <x-boton variante="secundario" tamano="chico" type="button" wire:click="guardarObservaciones({{ $montaje->id }})"
                 class="mt-2">
            Guardar observaciones
        </x-boton>
    </div>

    {{-- Estado del montaje --}}
    <div class="border-t border-gray-100 pt-5">
        <h3 class="mb-2 text-[15px] font-semibold text-gray-900">Estado del montaje</h3>

        @if ($montaje->estado === \App\Enums\EstadoPreparacion::Pendiente)
            <x-boton type="button" wire:click="cambiarEstado({{ $montaje->id }}, 'en_preparacion')" class="w-full sm:w-auto">
                Empezar el montaje
            </x-boton>
        @elseif ($montaje->estado === \App\Enums\EstadoPreparacion::EnPreparacion)
            @if ($montaje->sala_id === null)
                <x-aviso tono="atencion">
                    Asigna una sala antes de dar el escenario por preparado.
                </x-aviso>
            @else
                <x-boton type="button" wire:click="cambiarEstado({{ $montaje->id }}, 'preparado')" class="w-full sm:w-auto">
                    Marcar como preparado
                </x-boton>
            @endif
        @else
            <p class="mb-2 text-[14px] text-gray-600">Este escenario ya está preparado.</p>
            <x-boton variante="secundario" type="button" wire:click="cambiarEstado({{ $montaje->id }}, 'en_preparacion')" class="w-full sm:w-auto">
                Volver a en preparación
            </x-boton>
        @endif
    </div>
</div>
