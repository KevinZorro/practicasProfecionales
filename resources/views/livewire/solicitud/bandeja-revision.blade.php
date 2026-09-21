<div class="space-y-4">

    <div>
        <label for="filtro-estado" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Estado</label>
        <select wire:model.live="estado" id="filtro-estado"
                class="rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
            <option value="">Todos</option>
            @foreach ($estados as $unEstado)
                <option value="{{ $unEstado->value }}">{{ $unEstado->etiqueta() }}</option>
            @endforeach
        </select>
    </div>

    @if ($sinCobertura !== [])
        {{-- RF66: el inventario dejó de dar para prácticas ya aprobadas.
             Solo se avisa: qué hacer con ellas lo decide el laboratorio. --}}
        <p class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
            {{ trans_choice(
                'Hay :count práctica aprobada sin unidades suficientes|Hay :count prácticas aprobadas sin unidades suficientes',
                count($sinCobertura),
                ['count' => count($sinCobertura)],
            ) }}:
            alguno de sus equipos dejó de estar operativo después de aprobarlas. Están marcadas abajo.
        </p>
    @endif

    @if ($solicitudes->isEmpty())
        <x-mensaje-vacio titulo="No hay solicitudes" descripcion="Cuando un docente pida un escenario aparecerá aquí." />
    @else
        <ul class="space-y-3">
            @foreach ($solicitudes as $solicitud)
                <li wire:key="solicitud-{{ $solicitud->id }}">
                    <x-tarjeta>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $solicitud->casoClinico->nombre }}</p>
                                <p class="truncate text-sm text-gray-600">{{ $solicitud->docente->nombre }} · {{ $solicitud->materia->nombre }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ $solicitud->tipo->etiqueta() }}</span>
                                <x-etiqueta-estado :estado="$solicitud->estado" />
                            </div>
                        </div>

                        @if (in_array($solicitud->id, $sinCobertura, true))
                            <p class="mt-2 text-sm text-rose-800">
                                Sin unidades suficientes: alguno de los equipos de esta práctica dejó de
                                estar operativo después de aprobarla.
                            </p>
                        @endif

                        <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <x-dato etiqueta="Fecha">{{ $solicitud->fecha->format('d/m/Y') }}</x-dato>
                            <x-dato etiqueta="Hora">{{ substr($solicitud->hora_inicio, 0, 5) }}–{{ substr($solicitud->hora_fin, 0, 5) }}</x-dato>
                            <x-dato etiqueta="Estudiantes">{{ $solicitud->cantidad_estudiantes }}</x-dato>
                            <x-dato etiqueta="Sala">
                                @if ($solicitud->preparacion?->sala)
                                    {{ $solicitud->preparacion->sala->nombre }}
                                @else
                                    <span class="text-gray-500">Se asigna al preparar</span>
                                @endif
                            </x-dato>
                        </dl>

                        <div class="mt-3 flex flex-wrap gap-2 border-t border-gray-200 pt-3">
                            <x-boton variante="secundario" type="button" wire:click="abrir({{ $solicitud->id }})">
                                {{ $abierta === $solicitud->id ? 'Ocultar detalle' : 'Ver detalle' }}
                            </x-boton>

                            @can('revisar', $solicitud)
                                @if ($solicitud->estado === \App\Enums\EstadoSolicitud::Pendiente)
                                    <x-boton variante="secundario" type="button" wire:click="revisar({{ $solicitud->id }})">
                                        Marcar como revisada
                                    </x-boton>
                                @endif
                            @endcan

                            @can('aprobar', $solicitud)
                                @if ($solicitud->estado === \App\Enums\EstadoSolicitud::Revisada)
                                    <x-boton type="button" wire:click="aprobar({{ $solicitud->id }})">Aprobar</x-boton>
                                @endif
                            @endcan

                            @can('rechazar', $solicitud)
                                @if (in_array($solicitud->estado, [\App\Enums\EstadoSolicitud::Pendiente, \App\Enums\EstadoSolicitud::Revisada], true))
                                    <x-boton variante="secundario" type="button" wire:click="pedirMotivo({{ $solicitud->id }})">Rechazar</x-boton>
                                @endif
                            @endcan
                        </div>

                        @if ($rechazando === $solicitud->id)
                            <div class="mt-3 space-y-2 rounded-md bg-rose-50 p-3 ring-1 ring-inset ring-rose-600/20">
                                <label for="motivo-{{ $solicitud->id }}" class="block text-sm font-medium text-rose-900">
                                    Motivo del rechazo <span class="font-normal">(opcional)</span>
                                </label>
                                <textarea wire:model="motivoRechazo" id="motivo-{{ $solicitud->id }}" rows="2"
                                          class="w-full rounded-md border border-gray-300 text-sm focus:border-rose-600 focus:ring-rose-600"></textarea>
                                <p class="text-xs text-rose-900">El docente lo verá en su historial y le llegará por correo.</p>
                                <div class="flex flex-wrap gap-2">
                                    <x-boton type="button" wire:click="rechazar({{ $solicitud->id }})">Confirmar rechazo</x-boton>
                                    <x-boton variante="secundario" type="button" wire:click="cerrar">Cancelar</x-boton>
                                </div>
                            </div>
                        @endif

                        @if ($abierta === $solicitud->id && $detalle !== null)
                            <div class="mt-3 border-t border-gray-200 pt-3">
                                @if ($faltantes !== [])
                                    <p class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
                                        Se pidieron más unidades de las que quedan libres en esa franja. El docente no ve
                                        disponibilidad al solicitar, así que puede pedir de más. Es un aviso, no un impedimento.
                                    </p>
                                @endif

                                @if ($detalle->observaciones)
                                    <div class="mb-3">
                                        <x-dato etiqueta="Observaciones del docente">{{ $detalle->observaciones }}</x-dato>
                                    </div>
                                @endif

                                <h3 class="mb-2 text-sm font-semibold text-gray-900">Equipos solicitados</h3>
                                <x-lista-equipos :items="$detalle->items" :faltantes="$faltantes" />
                            </div>
                        @endif
                    </x-tarjeta>
                </li>
            @endforeach
        </ul>

        <div>{{ $solicitudes->links() }}</div>
    @endif
</div>
