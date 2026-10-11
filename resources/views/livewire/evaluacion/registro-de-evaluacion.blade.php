@php
    $sinResultado = $evaluacion->estudiantes->whereNull('resultado')->count();
@endphp

<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">{{ $errorDeRegla }}</x-aviso>
    @endif

    {{-- El nombre de la evaluación es el título de la página; aquí, cómo va. --}}
    <x-tarjeta>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-[15px] text-gray-700">
                {{ trans_choice(':count estudiante|:count estudiantes', $evaluacion->estudiantes->count(), ['count' => $evaluacion->estudiantes->count()]) }}
                @if ($evaluacion->estudiantes->isNotEmpty())
                    · {{ $sinResultado === 0 ? 'todos con resultado' : trans_choice(':count sin resultado|:count sin resultado', $sinResultado, ['count' => $sinResultado]) }}
                @endif
            </p>
            <x-etiqueta-estado :estado="$evaluacion->estado" />
        </div>
        <x-slot:pie>
            El resultado lo decides tú: marcar ítems del checklist no aprueba ni reprueba a nadie.
        </x-slot:pie>
    </x-tarjeta>

    @if ($editable && $candidatos['estudiantes']->isNotEmpty())
        <x-tarjeta titulo="Estudiantes de la sesión por agregar">
            <ul class="-my-1 divide-y divide-gray-100">
                @foreach ($candidatos['estudiantes'] as $candidato)
                    @php($impedimentos = $candidatos['impedimentos'][$candidato->id])
                    <li wire:key="candidato-{{ $candidato->id }}" class="flex flex-wrap items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <p class="text-[15px] text-gray-900">{{ $candidato->nombre }}</p>
                            @foreach ($impedimentos as $impedimento)
                                <p class="text-[14px] text-rojo-800">{{ $impedimento->descripcion() }}</p>
                            @endforeach
                        </div>
                        @if ($impedimentos === [])
                            <x-boton variante="secundario" tamano="chico" type="button" wire:click="agregar({{ $candidato->id }})">Agregar</x-boton>
                        @else
                            <x-insignia tono="peligro">No se puede evaluar</x-insignia>
                        @endif
                    </li>
                @endforeach
            </ul>
            <x-slot:pie>
                <x-boton tamano="chico" type="button" wire:click="agregarHabilitados">Agregar a todos los que pueden ingresar</x-boton>
            </x-slot:pie>
        </x-tarjeta>
    @endif

    @if ($evaluacion->estudiantes->isEmpty())
        <x-mensaje-vacio titulo="Todavía no hay estudiantes en esta evaluación" descripcion="Agrégalos desde la lista de la sesión." />
    @endif

    @foreach ($evaluacion->estudiantes as $registro)
        @php($cumplidos = $registro->items->filter(fn ($i) => (bool) $i->pivot->cumplido)->pluck('id')->all())
        <x-tarjeta wire:key="registro-{{ $registro->id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex min-w-0 items-center gap-3">
                    <x-iniciales :nombre="$registro->estudiante->nombre" class="size-9 bg-gray-100 text-[13px] text-gray-700" />
                    <div class="min-w-0">
                        <p class="text-[16px] font-semibold leading-snug text-gray-900">{{ $registro->estudiante->nombre }}</p>
                        <p class="text-[13px] text-gray-500">
                            Intento {{ $registro->intento }} · {{ count($cumplidos) }} de {{ $evaluacion->items->count() }} ítems cumplidos
                        </p>
                    </div>
                </div>
                @if ($registro->resultado)
                    <x-etiqueta-estado :estado="$registro->resultado" />
                @else
                    <x-insignia tono="atencion">Sin resultado</x-insignia>
                @endif
            </div>

            <ul class="mt-4 divide-y divide-gray-100 rounded-xl bg-gray-50">
                @foreach ($evaluacion->items as $item)
                    @php($cumplido = in_array($item->id, $cumplidos, true))
                    <li wire:key="item-{{ $registro->id }}-{{ $item->id }}">
                        @if ($editable)
                            <label class="flex cursor-pointer items-start gap-3 px-3.5 py-3 text-[15px] leading-snug text-gray-900">
                                <input type="checkbox" @checked($cumplido)
                                       wire:click="alternarItem({{ $registro->id }}, {{ $item->id }})"
                                       class="casilla mt-px">
                                <span>{{ $item->descripcion }}</span>
                            </label>
                        @else
                            <p class="flex items-start gap-3 px-3.5 py-3 text-[15px] leading-snug">
                                <x-marca-de-checklist :cumplido="$cumplido" />
                                <span @class(['text-gray-900' => $cumplido, 'text-gray-600' => ! $cumplido])>{{ $item->descripcion }}<span class="sr-only">{{ $cumplido ? ' (cumplido)' : ' (no cumplido)' }}</span></span>
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($editable)
                <div class="mt-4">
                    <p class="campo-etiqueta" id="resultado-{{ $registro->id }}">Resultado</p>
                    <div class="inline-flex rounded-full bg-gray-100 p-1" role="group" aria-labelledby="resultado-{{ $registro->id }}">
                        @foreach ($resultados as $opcion)
                            @php($elegido = $registro->resultado === $opcion)
                            <button type="button" wire:click="resultado({{ $registro->id }}, '{{ $opcion->value }}')" aria-pressed="{{ $elegido ? 'true' : 'false' }}"
                                    @class([
                                        'min-h-10 rounded-full px-4 text-[14px] font-semibold transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-rojo',
                                        'bg-emerald-700 text-white' => $elegido && $opcion === \App\Enums\ResultadoEvaluacion::Aprobado,
                                        'bg-rojo text-white' => $elegido && $opcion === \App\Enums\ResultadoEvaluacion::NoAprobado,
                                        'text-gray-700 hover:text-gray-900' => ! $elegido,
                                    ])>
                                {{ $opcion->etiqueta() }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4">
                    <label for="observaciones-{{ $registro->id }}" class="campo-etiqueta">Observaciones</label>
                    <textarea wire:model="observaciones.{{ $registro->id }}" wire:blur="guardarObservaciones({{ $registro->id }})"
                              id="observaciones-{{ $registro->id }}" rows="2" class="campo"></textarea>
                    @error('observaciones.'.$registro->id) <p class="campo-error">{{ $message }}</p> @enderror
                    <p class="campo-ayuda">Se guardan al salir del campo.</p>
                </div>

                <x-slot:pie>
                    <x-boton variante="texto" tamano="chico" type="button" wire:click="quitar({{ $registro->id }})"
                             wire:confirm="¿Quitar a {{ $registro->estudiante->nombre }} de esta evaluación?"
                             class="-ml-2 text-rojo-700 hover:text-rojo-800">Quitar de la evaluación</x-boton>
                </x-slot:pie>
            @elseif ($registro->observaciones)
                <dl class="mt-4"><x-dato etiqueta="Observaciones">{{ $registro->observaciones }}</x-dato></dl>
            @endif
        </x-tarjeta>
    @endforeach

    @if ($editable && $evaluacion->estudiantes->isNotEmpty())
        <div class="sticky bottom-3 z-20 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white p-4 shadow-[0_12px_32px_-12px_rgb(0_0_0/0.28)] ring-1 ring-gray-300 sm:bottom-5 sm:px-5">
            <p class="text-[14px] text-gray-600">
                @if ($sinResultado === 0)
                    Todos tienen resultado. Al finalizar, los estudiantes los verán.
                @else
                    {{ trans_choice('Falta :count estudiante por resultado.|Faltan :count estudiantes por resultado.', $sinResultado, ['count' => $sinResultado]) }}
                    Todos deben tener resultado antes de finalizar.
                @endif
            </p>
            <x-boton type="button" wire:click="finalizar"
                     wire:confirm="Al finalizar, la evaluación ya no se puede modificar y los estudiantes verán sus resultados. ¿Continuar?">
                Finalizar evaluación
            </x-boton>
        </div>
    @endif
</div>
