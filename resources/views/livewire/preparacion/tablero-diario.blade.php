{{--
    Sin wire:key en la raíz: Livewire localiza el componente por su elemento
    raíz, así que una clave que cambia al cambiar la fecha se lo hace perder
    ("Could not find Livewire component in DOM tree").
--}}
<div class="space-y-5">

    {{-- Selector de fecha. Botones grandes: se usa de pie y con prisa. --}}
    <div class="flex flex-wrap items-end gap-x-4 gap-y-3">
        <div class="flex min-w-0 flex-1 basis-64 items-end gap-2 sm:max-w-md">
            <div class="min-w-0 flex-1">
                <label for="fecha" class="campo-etiqueta">Fecha</label>
                <input type="date" wire:model.live="fecha" id="fecha" class="campo">
            </div>
            <x-boton variante="secundario" type="button" wire:click="irA('{{ now()->toDateString() }}')">
                Hoy
            </x-boton>
        </div>
        <p class="text-[14px] text-gray-500 sm:pb-2.5">
            {{ trans_choice(':count escenario por montar|:count escenarios por montar', $montajes->count(), ['count' => $montajes->count()]) }}
            · {{ $montajes->where('estado', \App\Enums\EstadoPreparacion::Preparado)->count() }} listos
        </p>
    </div>

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
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-[1.375rem] font-extrabold leading-tight tracking-[-0.02em] text-gray-900">
                                    {{ substr($solicitud->hora_inicio, 0, 5) }}–{{ substr($solicitud->hora_fin, 0, 5) }}
                                </p>
                                <a href="{{ route('panel.solicitudes.ver', $solicitud) }}" class="mt-0.5 block text-[16px] font-semibold leading-snug text-gray-900 underline decoration-transparent underline-offset-4 hover:decoration-gray-900">{{ $solicitud->casoClinico->nombre }}</a>
                                <p class="text-[14px] leading-snug text-gray-500">
                                    {{ ($solicitud->docenteQueDicta ?? $solicitud->docente)->nombre }}{{ $solicitud->docenteQueDicta ? ' (reemplaza a '.$solicitud->docente->nombre.')' : '' }} · {{ $solicitud->materia->nombre }}
                                </p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                <x-tipo-de-sesion :tipo="$solicitud->tipo" />
                                {{-- Recién cambiado, se tiñe un momento: en el celular el aviso queda lejos. --}}
                                <span class="{{ \Illuminate\Support\Arr::toCssClasses(['-mx-1.5 -my-1 rounded-full px-1.5 py-1', 'recien' => $estadoRecienCambiado === $montaje->id]) }}">
                                    <x-etiqueta-estado :estado="$montaje->estado" />
                                </span>
                            </div>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3">
                            <x-dato etiqueta="Estudiantes">{{ $solicitud->cantidad_estudiantes }}{{ $solicitud->grupo ? ' · grupo '.$solicitud->grupo : '' }}</x-dato>
                            {{-- Unidas, cada sala en su línea y a todo el ancho: un «+» dentro
                                 de un texto largo no se leía como un solo salón. Recién
                                 asignadas, se tiñen un momento: en el celular el aviso
                                 queda lejos de la tarjeta. --}}
                            @php($unidas = $montaje->salas->count() > 1)
                            <x-dato etiqueta="Sala" class="{{ \Illuminate\Support\Arr::toCssClasses(['-mx-2 -my-1 rounded-[10px] px-2 py-1', 'col-span-2' => $unidas, 'recien' => $recienAsignada === $montaje->id]) }}">
                                @if ($unidas)
                                    @foreach ($montaje->salas as $sala)
                                        <span class="block font-semibold">{{ $sala->nombreCompleto() }}</span>
                                    @endforeach
                                    <span class="mt-1 flex items-center gap-1.5 text-[13px] text-gray-600">
                                        <svg class="h-3 w-[1.375rem] shrink-0" viewBox="0 0 22 12" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                                            <rect x="1" y="1" width="20" height="10" rx="3"/>
                                            <path d="M11 1v10" stroke-dasharray="1.6 1.6"/>
                                        </svg>
                                        Unidas abriendo la cortina · caben {{ $montaje->salas->sum('capacidad') }}
                                    </span>
                                @elseif ($montaje->salas->isNotEmpty())
                                    <span class="font-semibold">{{ $montaje->salas->first()->nombreCompleto() }}</span>
                                @else
                                    <span class="font-medium text-amber-800">Sin asignar</span>
                                @endif
                            </x-dato>
                        </dl>

                        {{-- Quién no puede entrar se ve antes de abrir la puerta (RF70). --}}
                        @php($noPueden = $noPuedenIngresar[$montaje->id] ?? ['docente' => false, 'estudiantes' => 0])
                        @if ($noPueden['docente'] || $noPueden['estudiantes'] > 0)
                            <x-aviso tono="peligro" class="mt-4">
                                @if ($noPueden['docente'])
                                    El docente no puede ingresar.
                                @endif
                                @if ($noPueden['estudiantes'] > 0)
                                    {{ $noPueden['estudiantes'] }} {{ $noPueden['estudiantes'] === 1 ? 'estudiante no puede' : 'estudiantes no pueden' }} ingresar.
                                @endif
                                <a href="{{ route('panel.solicitudes.participantes', $solicitud) }}" class="font-semibold underline underline-offset-4">Ver quiénes</a>
                            </x-aviso>
                        @endif

                        @if (($delIngeniero[$montaje->id] ?? []) !== [])
                            <p class="mt-3 text-[14px] font-medium text-amber-800">
                                {{ count($delIngeniero[$montaje->id]) }} {{ count($delIngeniero[$montaje->id]) === 1 ? 'elemento requiere' : 'elementos requieren' }} al ingeniero (alta fidelidad).
                            </p>
                        @endif

                        @php($alistados = $montaje->items->where('pivot.alistado', true)->count())
                        @php($total = $montaje->items->count())

                        <x-progreso-alistamiento :alistados="$alistados" :total="$total" class="mt-4" />

                        @if ($montaje->estado === \App\Enums\EstadoPreparacion::Preparado && $montaje->preparado_at)
                            <p class="mt-2 text-[13px] text-gray-500">
                                Montado el {{ $montaje->preparado_at->format('d/m/Y H:i') }}
                            </p>
                        @endif

                        <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                            <x-boton :variante="$abierta === $montaje->id ? 'secundario' : 'primario'" type="button" wire:click="abrir({{ $montaje->id }})"
                                     class="w-full sm:w-auto" aria-expanded="{{ $abierta === $montaje->id ? 'true' : 'false' }}">
                                {{ $abierta === $montaje->id ? 'Cerrar' : 'Preparar escenario' }}
                            </x-boton>
                            @can('reprogramar', $solicitud)
                                <x-boton variante="texto" href="{{ route('panel.solicitudes.novedades', $solicitud) }}" class="w-full sm:w-auto">
                                    Cambiar docente o fecha
                                </x-boton>
                            @endcan
                        </div>

                        @if ($abierta === $montaje->id && $detalle !== null)
                            @include('livewire.preparacion.partes.panel-de-montaje', [
                                'montaje' => $detalle,
                                'salasLibres' => $salasLibres,
                                'conflicto' => $conflictos[$montaje->id] ?? null,
                                'delIngeniero' => $delIngeniero[$montaje->id] ?? [],
                            ])
                        @endif
                    </x-tarjeta>
                </li>
            @endforeach
        </ul>
    @endif
</div>
