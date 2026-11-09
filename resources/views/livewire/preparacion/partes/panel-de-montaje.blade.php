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

    {{--
        Salas: de una a tres, que se unen abriendo las cortinas (P16).
        Mientras se eligen, el salón enseña cómo quedan y si el grupo cabe
        (resources/js/salon.js); quien lo decide es asignarSalas, en el
        servidor.
    --}}
    @php
        $grupo = $montaje->solicitud->cantidad_estudiantes;
        $maximo = \App\Services\PreparacionService::MAXIMO_DE_SALAS;
    @endphp
    <fieldset x-data="salonDeLaSesion({
                  salas: @js($salasLibres->map(fn ($sala) => ['id' => $sala->id, 'nombre' => $sala->nombre, 'capacidad' => $sala->capacidad])->values()),
                  grupo: {{ $grupo }},
                  maximo: {{ $maximo }},
                  asignadas: @js($montaje->salas->pluck('id')->values()),
              })"
              x-on:salas-asignadas.window="recordarAsignadas($event.detail, {{ $montaje->id }})">
        <legend class="mb-1 text-[15px] font-semibold text-gray-900">Sala</legend>
        <p class="mb-3 text-[14px] text-gray-600">
            El grupo es de {{ $grupo }} estudiantes. Si no cabe en una sala, une hasta {{ $maximo }}
            abriendo la cortina: la capacidad se suma.
        </p>

        @if ($salasLibres->isEmpty())
            <x-aviso tono="atencion">No queda ninguna sala libre en esta franja.</x-aviso>
        @else
            {{-- La fila entera es el objetivo del pulgar, como en el material. --}}
            <div class="space-y-2">
                @foreach ($salasLibres as $sala)
                    <label wire:key="sala-{{ $montaje->id }}-{{ $sala->id }}"
                           class="flex min-h-14 cursor-pointer items-center gap-3 rounded-xl bg-gray-50 px-3.5 py-2 transition-colors duration-150 hover:bg-gray-100 has-[:checked]:bg-white has-[:checked]:ring-[1.5px] has-[:checked]:ring-inset has-[:checked]:ring-gray-900 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50">
                        <input type="checkbox" value="{{ $sala->id }}" wire:model="salasElegidas"
                               x-bind:disabled="completo && ! elegida({{ $sala->id }})"
                               class="casilla">
                        <span class="min-w-0 flex-1">
                            <span class="block text-[15px] font-semibold leading-snug text-gray-900">{{ $sala->nombre }}</span>
                            @if ($sala->ubicacion())
                                <span class="block text-[13px] leading-snug text-gray-500">{{ $sala->ubicacion() }}</span>
                            @endif
                            @if ($sala->del_escenario)
                                <span class="mt-1 inline-flex h-5 items-center rounded-full bg-gray-100 px-2 text-[11px] font-semibold text-gray-700">Recomendada para este escenario</span>
                            @endif
                        </span>
                        <span class="shrink-0 text-[14px] text-gray-700">caben <span class="font-bold text-gray-900">{{ $sala->capacidad }}</span></span>
                    </label>
                @endforeach
            </div>
            <p x-show="completo" x-cloak class="mt-2 text-[13px] text-gray-600">Ya son {{ $maximo }}, el máximo por sesión.</p>

            {{-- Cómo queda el salón. Repite lo que dice el texto de abajo, así que el lector de pantalla lo salta. --}}
            <div class="mt-5 border-t border-gray-100 pt-4">
                <div class="flex min-h-6 items-center gap-2">
                    <h4 class="text-[13px] font-semibold text-gray-500">Así queda</h4>
                    <span x-show="elegidas.length > 1" x-cloak x-transition.opacity.duration.150ms
                          class="inline-flex h-6 items-center rounded-full bg-gray-100 px-2.5 text-[12px] font-semibold text-gray-700">Unidas</span>
                </div>

                <p x-show="elegidas.length === 0"
                   class="mt-2.5 flex min-h-[5.5rem] items-center justify-center rounded-xl border border-dashed border-gray-300 px-4 text-center text-[13px] text-gray-500">
                    Marca una sala y aquí ves cómo queda el salón.
                </p>

                <div x-show="elegidas.length > 0" x-cloak x-bind:data-salas="elegidas.length" class="salon mt-2.5" aria-hidden="true">
                    @for ($posicion = 0; $posicion < $maximo; $posicion++)
                        @if ($posicion > 0)
                            <div class="salon-cortina" x-bind:class="cortina({{ $posicion - 1 }})">
                                {{-- Los pliegues de la cortina de hospital, colgados de su riel. --}}
                                <svg viewBox="0 0 12 72" preserveAspectRatio="none" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round">
                                    <path vector-effect="non-scaling-stroke" d="M1 1h10"/>
                                    <path vector-effect="non-scaling-stroke" d="M6 1L2 9l8 8-8 8 8 8-8 8 8 8-8 8 8 8-4 7"/>
                                </svg>
                            </div>
                        @endif
                        <div class="salon-sala" x-bind:class="ranura({{ $posicion }})">
                            <div class="salon-sala-contenido">
                                <span class="salon-sala-nombre" x-text="enLugar({{ $posicion }})?.nombre ?? ''"></span>
                                <span class="salon-sala-caben" x-text="enLugar({{ $posicion }}) ? 'caben ' + enLugar({{ $posicion }}).capacidad : ''"></span>
                            </div>
                        </div>
                    @endfor
                </div>

                <div x-show="elegidas.length > 0" x-cloak class="mt-3.5">
                    <div class="flex items-center justify-between gap-3 text-[13px] text-gray-600">
                        <span><span class="font-semibold text-gray-900">{{ $grupo }}</span> estudiantes</span>
                        <span>caben <span class="font-semibold text-gray-900" x-text="total"></span></span>
                    </div>
                    <div class="mt-1.5 flex h-2 overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                        <span class="salon-medidor" x-bind:class="cabe() ? 'bg-emerald-700' : 'bg-gray-900'" x-bind:style="{ width: anchoOcupado() }"></span>
                        <span class="salon-medidor bg-amber-500" x-bind:style="{ width: anchoQueFalta() }"></span>
                    </div>
                </div>

                <p class="mt-2.5 text-[14px] leading-snug text-gray-600" aria-live="polite" x-text="estado()"
                   x-bind:class="elegidas.length === 0 ? '' : (cabe() ? 'font-medium !text-emerald-700' : 'font-medium !text-amber-800')">
                    Elige la sala o las salas donde se monta.
                </p>
            </div>

            <x-boton cargando="Asignando…" type="button" wire:click="asignarSalas({{ $montaje->id }})" x-show="! sinCambios" x-cloak class="mt-4 w-full sm:w-auto">
                <span x-text="etiqueta()">{{ $montaje->salas->isNotEmpty() ? 'Cambiar' : 'Asignar' }}</span>
            </x-boton>
            <p x-show="sinCambios" x-cloak class="mt-4 flex items-center gap-2 text-[14px] text-gray-600">
                <svg class="size-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                <span x-text="yaAsignadas()"></span>
            </p>
            @error('salasElegidas') <p class="campo-error">{{ $message }}</p> @enderror
            @error('salasElegidas.*') <p class="campo-error">{{ $message }}</p> @enderror
        @endif
    </fieldset>

    {{-- Alistamiento --}}
    <div>
        <h3 class="mb-1 text-[15px] font-semibold text-gray-900">Material</h3>

        {{-- Lo que monta el ingeniero va aparte y primero: hay que llamarlo antes (RF72). --}}
        @php($grupos = $montaje->items->groupBy(fn ($item) => in_array($item->id, $delIngeniero, true) ? 'ingeniero' : $item->tipo->value)->sortKeysUsing(fn ($a, $b) => ($b === 'ingeniero') <=> ($a === 'ingeniero')))

        @forelse ($grupos as $tipo => $delTipo)
            <h4 @class(['mb-2 mt-4 text-[13px] font-semibold', 'text-amber-800' => $tipo === 'ingeniero', 'text-gray-500' => $tipo !== 'ingeniero'])>
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
        <x-boton cargando="Guardando…" variante="secundario" tamano="chico" type="button" wire:click="guardarObservaciones({{ $montaje->id }})"
                 class="mt-2">
            Guardar observaciones
        </x-boton>
    </div>

    {{-- Estado del montaje --}}
    <div class="border-t border-gray-100 pt-5">
        <h3 class="mb-2 text-[15px] font-semibold text-gray-900">Estado del montaje</h3>

        @if ($montaje->estado === \App\Enums\EstadoPreparacion::Pendiente)
            <x-boton cargando="Guardando…" type="button" wire:click="cambiarEstado({{ $montaje->id }}, 'en_preparacion')" class="w-full sm:w-auto">
                Empezar el montaje
            </x-boton>
        @elseif ($montaje->estado === \App\Enums\EstadoPreparacion::EnPreparacion)
            @if ($montaje->salas->isEmpty())
                <x-aviso tono="atencion">
                    Asigna una sala antes de dar el escenario por preparado.
                </x-aviso>
            @else
                <x-boton cargando="Guardando…" type="button" wire:click="cambiarEstado({{ $montaje->id }}, 'preparado')" class="w-full sm:w-auto">
                    Marcar como preparado
                </x-boton>
            @endif
        @else
            <p class="mb-2 text-[14px] text-gray-600">Este escenario ya está preparado.</p>
            <x-boton cargando="Guardando…" variante="secundario" type="button" wire:click="cambiarEstado({{ $montaje->id }}, 'en_preparacion')" class="w-full sm:w-auto">
                Volver a en preparación
            </x-boton>
        @endif
    </div>
</div>
