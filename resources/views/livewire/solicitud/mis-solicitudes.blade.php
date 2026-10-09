<div class="space-y-4">

    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <label for="filtro-estado" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Estado</label>
            <select wire:model.live="estado" id="filtro-estado"
                    class="rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                <option value="">Todos</option>
                @foreach ($estados as $unEstado)
                    <option value="{{ $unEstado->value }}">{{ $unEstado->etiqueta() }}</option>
                @endforeach
            </select>
        </div>

        <x-boton href="{{ route('panel.solicitudes.nueva') }}">Solicitar escenario</x-boton>
    </div>

    @if ($solicitudes->isEmpty())
        <x-mensaje-vacio
            titulo="No tienes solicitudes"
            descripcion="Cuando pidas un escenario clínico aparecerá aquí con su estado."
        />
    @else
        <ul class="space-y-3">
            @foreach ($solicitudes as $solicitud)
                <li wire:key="solicitud-{{ $solicitud->id }}">
                    <x-tarjeta>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $solicitud->casoClinico->nombre }}</p>
                                <p class="truncate text-sm text-gray-600">{{ $solicitud->materia->nombre }}</p>
                                @if ($solicitud->docente_que_dicta_id === auth()->id())
                                    <p class="text-xs font-medium text-sky-800">La dictas en reemplazo de {{ $solicitud->docente->nombre }}</p>
                                @elseif ($solicitud->docenteQueDicta)
                                    <p class="text-xs font-medium text-amber-800">La dicta {{ $solicitud->docenteQueDicta->nombre }} en tu reemplazo</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ $solicitud->tipo->etiqueta() }}</span>
                                <x-etiqueta-estado :estado="$solicitud->estado" />
                            </div>
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <x-dato etiqueta="Fecha">{{ $solicitud->fecha->format('d/m/Y') }}</x-dato>
                            <x-dato etiqueta="Hora">{{ substr($solicitud->hora_inicio, 0, 5) }}–{{ substr($solicitud->hora_fin, 0, 5) }}</x-dato>
                            <x-dato etiqueta="Estudiantes">{{ $solicitud->cantidad_estudiantes }}{{ $solicitud->grupo ? ' · grupo '.$solicitud->grupo : '' }}</x-dato>
                            <x-dato etiqueta="Sala">
                                @if ($solicitud->preparacion?->sala)
                                    {{ $solicitud->preparacion->sala->nombreCompleto() }}
                                @else
                                    <span class="text-gray-500">Aún sin asignar</span>
                                @endif
                            </x-dato>
                        </dl>

                        @if ($solicitud->estado === \App\Enums\EstadoSolicitud::Rechazada)
                            <div class="mt-3 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20">
                                <span class="font-medium">Motivo del rechazo:</span>
                                {{ $solicitud->motivo_rechazo ?: 'No se dejó un motivo escrito.' }}
                            </div>
                        @else
                            <div class="mt-3 border-t border-gray-200 pt-3">
                            <x-boton variante="secundario" href="{{ route('panel.solicitudes.participantes', $solicitud) }}" class="px-3 py-2">
                                Participantes
                            </x-boton>
                        </div>
                        @endif
                    </x-tarjeta>
                </li>
            @endforeach
        </ul>

        <div>{{ $solicitudes->links() }}</div>
    @endif
</div>
