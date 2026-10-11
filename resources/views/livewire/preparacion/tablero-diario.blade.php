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
                                <x-etiqueta-estado :estado="$montaje->estado" />
                            </div>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-3">
                            <x-dato etiqueta="Estudiantes">{{ $solicitud->cantidad_estudiantes }}{{ $solicitud->grupo ? ' · grupo '.$solicitud->grupo : '' }}</x-dato>
                            <x-dato etiqueta="Sala">
                                @if ($montaje->sala)
                                    <span class="font-semibold">{{ $montaje->sala->nombreCompleto() }}</span>
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
                                    Reprogramar o sustituir
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
