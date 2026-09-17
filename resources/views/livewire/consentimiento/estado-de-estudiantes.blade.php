<div class="space-y-4">

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
                            @if ($entrega)
                                <x-etiqueta-estado :estado="$entrega->estado" />
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/20">
                                    Sin entregar
                                </span>
                            @endif
                        </div>
                        @if ($entrega?->estado !== \App\Enums\EstadoConsentimiento::Verificado)
                            <p class="mt-2 text-sm text-amber-800">No tiene el consentimiento al día para {{ $periodo }}.</p>
                        @endif
                    </x-tarjeta>
                </li>
            @endforeach
        </ul>

        <div>{{ $estudiantes->links() }}</div>
    @endif
</div>
