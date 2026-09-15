{{--
    Panel de montaje de un escenario. Va dentro de la tarjeta del tablero,
    no en una pantalla aparte: quien monta necesita ver la hora y el caso
    clínico mientras marca el material.
--}}
<div class="mt-4 space-y-5 border-t border-gray-200 pt-4">

    @if ($conflicto)
        <p class="rounded-md bg-rose-50 px-3 py-2.5 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
            {{ $conflicto }}
        </p>
    @endif

    {{-- Sala --}}
    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">Sala</h3>

        @if ($salasLibres->isEmpty())
            <p class="rounded-md bg-amber-50 px-3 py-2.5 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
                No queda ninguna sala libre en esta franja.
            </p>
        @else
            <div class="flex flex-wrap items-end gap-2">
                <div class="min-w-0 flex-1">
                    <label for="sala-{{ $montaje->id }}" class="sr-only">Sala</label>
                    <select wire:model="salaElegida" id="sala-{{ $montaje->id }}"
                            class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-base focus:border-sky-600 focus:ring-sky-600">
                        <option value="">Elige una sala libre</option>
                        @foreach ($salasLibres as $sala)
                            <option value="{{ $sala->id }}">{{ $sala->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <x-boton type="button" wire:click="asignarSala({{ $montaje->id }})" class="px-4 py-2.5">
                    {{ $montaje->sala ? 'Cambiar' : 'Asignar' }}
                </x-boton>
            </div>
            @error('salaElegida') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
        @endif
    </div>

    {{-- Alistamiento --}}
    <div>
        <h3 class="mb-2 text-sm font-semibold text-gray-900">Material</h3>

        @php($porTipo = $montaje->items->groupBy(fn ($item) => $item->tipo->value))

        @forelse ($porTipo as $tipo => $delTipo)
            <h4 class="mb-2 mt-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                {{ \App\Enums\TipoItemInventario::from($tipo)->etiqueta() }}
            </h4>
            <ul class="space-y-2">
                @foreach ($delTipo as $item)
                    <li wire:key="item-{{ $montaje->id }}-{{ $item->id }}">
                        {{-- La etiqueta entera es el objetivo táctil: se marca
                             con el pulgar, no con la punta del dedo. --}}
                        <label class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 px-3 py-3 hover:bg-gray-50">
                            <input type="checkbox"
                                   wire:click="alternarItem({{ $montaje->id }}, {{ $item->id }})"
                                   @checked($item->pivot->alistado)
                                   class="size-6 shrink-0 rounded border-gray-300 text-sky-700 focus:ring-sky-600">
                            <span class="min-w-0 flex-1 text-base text-gray-900">{{ $item->nombre }}</span>
                            <span class="shrink-0 text-base font-medium text-gray-700">×{{ $item->pivot->cantidad }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>
        @empty
            <p class="text-sm text-gray-500">Este escenario no lleva equipos.</p>
        @endforelse
    </div>

    {{-- Observaciones --}}
    <div>
        <label for="observaciones-{{ $montaje->id }}" class="mb-1 block text-sm font-semibold text-gray-900">
            Observaciones del alistamiento
        </label>
        <textarea wire:model="observaciones" id="observaciones-{{ $montaje->id }}" rows="2"
                  class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-base focus:border-sky-600 focus:ring-sky-600"
                  placeholder="Algo que el docente deba saber al llegar."></textarea>
        @error('observaciones') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
        <x-boton variante="secundario" type="button" wire:click="guardarObservaciones({{ $montaje->id }})"
                 class="mt-2 px-4 py-2.5">
            Guardar observaciones
        </x-boton>
    </div>

    {{-- Estado del montaje --}}
    <div class="border-t border-gray-200 pt-4">
        <h3 class="mb-2 text-sm font-semibold text-gray-900">Estado del montaje</h3>

        @if ($montaje->estado === \App\Enums\EstadoPreparacion::Pendiente)
            <x-boton type="button" wire:click="cambiarEstado({{ $montaje->id }}, 'en_preparacion')"
                     class="w-full px-4 py-3 text-base sm:w-auto">
                Empezar el montaje
            </x-boton>
        @elseif ($montaje->estado === \App\Enums\EstadoPreparacion::EnPreparacion)
            @if ($montaje->sala_id === null)
                <p class="rounded-md bg-amber-50 px-3 py-2.5 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
                    Asigna una sala antes de dar el escenario por preparado.
                </p>
            @else
                <x-boton type="button" wire:click="cambiarEstado({{ $montaje->id }}, 'preparado')"
                         class="w-full px-4 py-3 text-base sm:w-auto">
                    Marcar como preparado
                </x-boton>
            @endif
        @else
            <p class="mb-2 text-sm text-gray-600">Este escenario ya está preparado.</p>
            <x-boton variante="secundario" type="button" wire:click="cambiarEstado({{ $montaje->id }}, 'en_preparacion')"
                     class="w-full px-4 py-3 text-base sm:w-auto">
                Volver a en preparación
            </x-boton>
        @endif
    </div>
</div>
