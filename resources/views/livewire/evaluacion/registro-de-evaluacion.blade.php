<div class="space-y-4">

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">{{ $errorDeRegla }}</p>
    @endif

    <x-tarjeta>
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-base font-semibold text-gray-900">{{ $evaluacion->tipoEvaluacion->nombre }}</p>
                <p class="text-sm text-gray-600">
                    {{ $evaluacion->solicitud->casoClinico->nombre }} · {{ $evaluacion->solicitud->materia->nombre }}
                    {{ $evaluacion->solicitud->grupo ? '· grupo '.$evaluacion->solicitud->grupo : '' }}
                    · {{ $evaluacion->solicitud->fecha->format('d/m/Y') }}
                </p>
            </div>
            <x-etiqueta-estado :estado="$evaluacion->estado" />
        </div>
        <x-slot:pie>
            El resultado lo decides tú: marcar ítems del checklist no aprueba ni reprueba a nadie.
        </x-slot:pie>
    </x-tarjeta>

    @if ($editable && $candidatos['estudiantes']->isNotEmpty())
        <x-tarjeta titulo="Estudiantes de la sesión por agregar">
            <ul class="divide-y divide-gray-200">
                @foreach ($candidatos['estudiantes'] as $candidato)
                    @php($impedimentos = $candidatos['impedimentos'][$candidato->id])
                    <li wire:key="candidato-{{ $candidato->id }}" class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <div class="min-w-0">
                            <p class="text-sm text-gray-900">{{ $candidato->nombre }}</p>
                            @foreach ($impedimentos as $impedimento)
                                <p class="text-sm text-rose-800">{{ $impedimento->descripcion() }}</p>
                            @endforeach
                        </div>
                        @if ($impedimentos === [])
                            <x-boton variante="secundario" type="button" wire:click="agregar({{ $candidato->id }})" class="px-3 py-2">Agregar</x-boton>
                        @else
                            <span class="text-xs text-gray-500">No se puede evaluar</span>
                        @endif
                    </li>
                @endforeach
            </ul>
            <x-slot:pie>
                <x-boton type="button" wire:click="agregarHabilitados" class="px-4 py-2.5">Agregar a todos los que pueden ingresar</x-boton>
            </x-slot:pie>
        </x-tarjeta>
    @endif

    @if ($evaluacion->estudiantes->isEmpty())
        <x-mensaje-vacio titulo="Todavía no hay estudiantes en esta evaluación" descripcion="Agrégalos desde la lista de la sesión." />
    @endif

    @foreach ($evaluacion->estudiantes as $registro)
        @php($cumplidos = $registro->items->filter(fn ($i) => (bool) $i->pivot->cumplido)->pluck('id')->all())
        <x-tarjeta wire:key="registro-{{ $registro->id }}">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-900">{{ $registro->estudiante->nombre }}</p>
                    <p class="text-xs text-gray-500">Intento {{ $registro->intento }}</p>
                </div>
                @if ($registro->resultado)
                    <x-etiqueta-estado :estado="$registro->resultado" />
                @else
                    <span class="text-xs text-amber-800">Sin resultado</span>
                @endif
            </div>

            <ul class="mt-3 space-y-1">
                @foreach ($evaluacion->items as $item)
                    @php($cumplido = in_array($item->id, $cumplidos, true))
                    <li wire:key="item-{{ $registro->id }}-{{ $item->id }}">
                        <label class="flex items-start gap-2 text-sm text-gray-800">
                            <input type="checkbox" @checked($cumplido) @disabled(! $editable)
                                   wire:click="alternarItem({{ $registro->id }}, {{ $item->id }})"
                                   class="mt-0.5 h-5 w-5 rounded border border-gray-300 text-sky-700">
                            <span>{{ $item->descripcion }}</span>
                        </label>
                    </li>
                @endforeach
            </ul>

            @if ($editable)
                <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Resultado de {{ $registro->estudiante->nombre }}">
                    @foreach ($resultados as $opcion)
                        <button type="button" wire:click="resultado({{ $registro->id }}, '{{ $opcion->value }}')"
                                @class([
                                    'rounded-md border px-4 py-2.5 text-sm font-medium',
                                    'border-sky-700 bg-sky-700 text-white' => $registro->resultado === $opcion,
                                    'border-gray-300 bg-white text-gray-700 hover:bg-gray-50' => $registro->resultado !== $opcion,
                                ])>
                            {{ $opcion->etiqueta() }}
                        </button>
                    @endforeach
                </div>

                <label for="observaciones-{{ $registro->id }}" class="mb-1 mt-3 block text-sm font-medium text-gray-700">Observaciones</label>
                <textarea wire:model="observaciones.{{ $registro->id }}" wire:blur="guardarObservaciones({{ $registro->id }})"
                          id="observaciones-{{ $registro->id }}" rows="2"
                          class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600"></textarea>
                @error('observaciones.'.$registro->id) <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror

                <button type="button" wire:click="quitar({{ $registro->id }})" wire:confirm="¿Quitar a {{ $registro->estudiante->nombre }} de esta evaluación?"
                        class="mt-2 rounded-md px-2 py-1 text-sm text-rose-700 hover:bg-rose-50">Quitar de la evaluación</button>
            @elseif ($registro->observaciones)
                <div class="mt-3"><x-dato etiqueta="Observaciones">{{ $registro->observaciones }}</x-dato></div>
            @endif
        </x-tarjeta>
    @endforeach

    @if ($editable && $evaluacion->estudiantes->isNotEmpty())
        <div class="flex flex-wrap items-center gap-3">
            <x-boton type="button" wire:click="finalizar"
                     wire:confirm="Al finalizar, la evaluación ya no se puede modificar y los estudiantes verán sus resultados. ¿Continuar?"
                     class="px-4 py-2.5">
                Finalizar evaluación
            </x-boton>
            <span class="text-sm text-gray-600">Todos deben tener resultado antes de finalizar.</span>
        </div>
    @endif
</div>
