{{--
    Sin wire:key en la raíz: Livewire localiza el componente por su elemento
    raíz, así que una clave que cambia al cambiar la fecha se lo hace perder
    ("Could not find Livewire component in DOM tree").
--}}
<div class="space-y-4">

    {{-- Selector de fecha. Botones grandes: se usa de pie y con prisa. --}}
    <x-tarjeta>
        <div class="flex flex-wrap items-end gap-2">
            <div class="min-w-0 flex-1">
                <label for="fecha" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Fecha</label>
                <input type="date" wire:model.live="fecha" id="fecha"
                       class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-base focus:border-sky-600 focus:ring-sky-600">
            </div>
            <x-boton variante="secundario" type="button" wire:click="irA('{{ now()->toDateString() }}')" class="px-4 py-2.5">
                Hoy
            </x-boton>
        </div>

        <p class="mt-3 text-sm text-gray-600">
            {{ trans_choice(':count escenario por montar|:count escenarios por montar', $montajes->count(), ['count' => $montajes->count()]) }}
            · {{ $montajes->where('estado', \App\Enums\EstadoPreparacion::Preparado)->count() }} listos
        </p>
    </x-tarjeta>

    @if ($montajes->isEmpty())
        <x-mensaje-vacio
            titulo="No hay escenarios para esta fecha"
            descripcion="Aquí aparecen los escenarios aprobados del día, ordenados por hora."
        />
    @else
        <ul class="space-y-3">
            @foreach ($montajes as $montaje)
                @php($solicitud = $montaje->solicitud)
                <li wire:key="montaje-{{ $montaje->id }}">
                    <x-tarjeta>
                        {{-- Cabecera: todo lo importante sin abrir nada. --}}
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-lg font-semibold tabular-nums text-gray-900">
                                    {{ substr($solicitud->hora_inicio, 0, 5) }}–{{ substr($solicitud->hora_fin, 0, 5) }}
                                </p>
                                <p class="truncate text-sm font-medium text-gray-900">{{ $solicitud->casoClinico->nombre }}</p>
                                <p class="truncate text-sm text-gray-600">
                                    {{ $solicitud->docente->nombre }} · {{ $solicitud->materia->nombre }}
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1">
                                <x-tipo-de-sesion :tipo="$solicitud->tipo" />
                                <x-etiqueta-estado :estado="$montaje->estado" />
                            </div>
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-3">
                            <x-dato etiqueta="Estudiantes">{{ $solicitud->cantidad_estudiantes }}</x-dato>
                            <x-dato etiqueta="Sala">
                                @if ($montaje->sala)
                                    <span class="font-medium">{{ $montaje->sala->nombre }}</span>
                                @else
                                    <span class="text-amber-800">Sin asignar</span>
                                @endif
                            </x-dato>
                        </dl>

                        @php($alistados = $montaje->items->where('pivot.alistado', true)->count())
                        @php($total = $montaje->items->count())

                        <x-progreso-alistamiento :alistados="$alistados" :total="$total" class="mt-3" />

                        @if ($montaje->estado === \App\Enums\EstadoPreparacion::Preparado && $montaje->preparado_at)
                            <p class="mt-2 text-xs text-gray-500">
                                Montado el {{ $montaje->preparado_at->format('d/m/Y H:i') }}
                            </p>
                        @endif

                        <div class="mt-3 border-t border-gray-200 pt-3">
                            <x-boton variante="secundario" type="button" wire:click="abrir({{ $montaje->id }})"
                                     class="w-full px-4 py-2.5 sm:w-auto">
                                {{ $abierta === $montaje->id ? 'Cerrar' : 'Preparar escenario' }}
                            </x-boton>
                        </div>

                        @if ($abierta === $montaje->id && $detalle !== null)
                            @include('livewire.preparacion.partes.panel-de-montaje', [
                                'montaje' => $detalle,
                                'salasLibres' => $salasLibres,
                                'conflicto' => $conflictos[$montaje->id] ?? null,
                            ])
                        @endif
                    </x-tarjeta>
                </li>
            @endforeach
        </ul>
    @endif
</div>
