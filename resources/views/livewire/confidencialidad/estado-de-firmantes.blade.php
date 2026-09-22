<div class="space-y-4">

    @if (session('estado'))
        <p class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ring-1 ring-inset ring-emerald-600/20" role="status">
            {{ session('estado') }}
        </p>
    @endif

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
            {{ $errorDeRegla }}
        </p>
    @endif

    <x-tarjeta>
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="sm:col-span-1">
                <label for="buscar" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Buscar</label>
                <input type="search" wire:model.live.debounce.400ms="busqueda" id="buscar" placeholder="Nombre, correo o código"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
            </div>
            <div>
                <label for="periodo" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Periodo</label>
                <input type="text" wire:model.live.debounce.400ms="periodo" id="periodo"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
            </div>
            <div>
                <label for="situacion" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Situación</label>
                <select wire:model.live="situacion" id="situacion"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Todos</option>
                    <option value="{{ \App\Livewire\Confidencialidad\EstadoDeFirmantes::LE_FALTA }}">Les falta</option>
                    <option value="{{ \App\Livewire\Confidencialidad\EstadoDeFirmantes::AL_DIA }}">Al día</option>
                </select>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600">
                {{ trans_choice(':count persona|:count personas', $firmantes->total(), ['count' => $firmantes->total()]) }}
                en {{ $periodo }}
            </p>
            <x-boton variante="secundario" type="button" wire:click="limpiarFiltros">Limpiar filtros</x-boton>
        </div>
    </x-tarjeta>

    @if ($firmantes->isEmpty())
        <x-mensaje-vacio titulo="No hay nadie que coincida" descripcion="Prueba a quitar algún filtro." />
    @else
        <ul class="space-y-2">
            @foreach ($firmantes as $firmante)
                @php($entrega = $firmante->formatosDeConfidencialidad->first())
                <li wire:key="firmante-{{ $firmante->id }}">
                    <x-tarjeta>
                        {{-- Columna y no fila: a 390 px el nombre y las etiquetas
                             no caben juntos y el nombre se corta. --}}
                        <div class="flex flex-col gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $firmante->nombre }}</p>
                                <p class="truncate text-sm text-gray-600">{{ $firmante->email }}</p>
                                @if ($firmante->codigo_institucional)
                                    <p class="truncate text-sm text-gray-500">Código {{ $firmante->codigo_institucional }}</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                {{-- El docente firma el mismo formato, pero quien revisa
                                     necesita distinguirlo de un vistazo (RF51-RF52). --}}
                                @if ($firmante->hasRole(\App\Enums\Rol::Docente->value))
                                    <span class="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-800 ring-1 ring-inset ring-violet-600/20">
                                        Docente
                                    </span>
                                @endif

                                {{-- La entrega en físico no es un estado del documento: convive
                                     con él, así que se pinta como etiqueta aparte (RF53). --}}
                                @if ($entrega?->entregadoEnFisico())
                                    <span class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-800 ring-1 ring-inset ring-sky-600/20">
                                        Entregado en físico
                                    </span>
                                @endif

                                @if ($entrega)
                                    <x-etiqueta-estado :estado="$entrega->estado" />
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/20">
                                        Sin entregar
                                    </span>
                                @endif
                            </div>
                        </div>

                        @if ($entrega?->entregadoEnFisico())
                            <p class="mt-2 text-sm text-gray-600">
                                Recibido por {{ $entrega->recibidoFisicoPor?->nombre ?? 'el laboratorio' }}
                                el {{ $entrega->recibido_fisico_at->format('d/m/Y') }}.
                                @if ($entrega->estado !== \App\Enums\EstadoFormatoConfidencialidad::Verificado)
                                    Puede ingresar; falta que suba el escaneo.
                                @endif
                            </p>
                        @elseif ($entrega?->estado !== \App\Enums\EstadoFormatoConfidencialidad::Verificado)
                            <p class="mt-2 text-sm text-amber-800">No tiene el formato al día para {{ $periodo }}.</p>

                            @can('marcarEntregaFisica', \App\Models\FormatoConfidencialidad::class)
                                <div class="mt-3">
                                    <x-boton variante="secundario" type="button"
                                             wire:click="marcarEntregaFisica({{ $firmante->id }})"
                                             wire:loading.attr="disabled">
                                        Recibí el formato en físico
                                    </x-boton>
                                </div>
                            @endcan
                        @endif
                    </x-tarjeta>
                </li>
            @endforeach
        </ul>

        <div>{{ $firmantes->links() }}</div>
    @endif
</div>
