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
                <input type="search" wire:model.live.debounce.400ms="busqueda" id="buscar" placeholder="Nombre o correo"
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
                    <option value="{{ \App\Livewire\Consentimiento\EstadoDeEstudiantes::LE_FALTA }}">Les falta</option>
                    <option value="{{ \App\Livewire\Consentimiento\EstadoDeEstudiantes::AL_DIA }}">Al día</option>
                </select>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600">
                {{ trans_choice(':count estudiante|:count estudiantes', $estudiantes->total(), ['count' => $estudiantes->total()]) }}
                en {{ $periodo }}
            </p>
            <x-boton variante="secundario" type="button" wire:click="limpiarFiltros">Limpiar filtros</x-boton>
        </div>
    </x-tarjeta>

    @if ($estudiantes->isEmpty())
        <x-mensaje-vacio titulo="No hay estudiantes que coincidan" descripcion="Prueba a quitar algún filtro." />
    @else
        <ul class="space-y-2">
            @foreach ($estudiantes as $estudiante)
                @php($entrega = $estudiante->consentimientos->first())
                <li wire:key="estudiante-{{ $estudiante->id }}">
                    <x-tarjeta>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $estudiante->nombre }}</p>
                                <p class="truncate text-sm text-gray-600">{{ $estudiante->email }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
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
                                @if ($entrega->estado !== \App\Enums\EstadoConsentimiento::Verificado)
                                    Puede ingresar; falta que suba el escaneo.
                                @endif
                            </p>
                        @elseif ($entrega?->estado !== \App\Enums\EstadoConsentimiento::Verificado)
                            <p class="mt-2 text-sm text-amber-800">No tiene el consentimiento al día para {{ $periodo }}.</p>

                            @can('marcarEntregaFisica', \App\Models\ConsentimientoEstudiante::class)
                                <div class="mt-3">
                                    <x-boton variante="secundario" type="button"
                                             wire:click="marcarEntregaFisica({{ $estudiante->id }})"
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

        <div>{{ $estudiantes->links() }}</div>
    @endif
</div>
