<div class="space-y-4">
    <x-tarjeta>
        <div class="grid gap-3 sm:grid-cols-4">
            <div>
                <label for="accion" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Acción</label>
                <select wire:model.live="accion" id="accion" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Todas</option>
                    @foreach ($acciones as $unaAccion)
                        <option value="{{ $unaAccion->value }}">{{ $unaAccion->etiqueta() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="persona" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Quién</label>
                <input type="search" wire:model.live.debounce.400ms="persona" id="persona" placeholder="Nombre"
                       class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
            </div>
            <div>
                <label for="desde" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Desde</label>
                <input type="date" wire:model.live="desde" id="desde" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                @error('desde') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="hasta" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Hasta</label>
                <input type="date" wire:model.live="hasta" id="hasta" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                @error('hasta') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600">{{ trans_choice(':count registro|:count registros', $registros->total(), ['count' => $registros->total()]) }}</p>
            <x-boton variante="secundario" type="button" wire:click="limpiarFiltros">Limpiar filtros</x-boton>
        </div>
    </x-tarjeta>

    @if ($registros->isEmpty())
        <x-mensaje-vacio titulo="No hay registros" descripcion="Aquí quedan las aprobaciones, rechazos, reprogramaciones, sustituciones, retiros, bloqueos y cambios de rol." />
    @else
        <x-tarjeta>
            <ul class="divide-y divide-gray-200">
                @foreach ($registros as $registro)
                    <li wire:key="bitacora-{{ $registro->id }}" class="py-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">{{ $registro->accion->etiqueta() }}</span>
                            <span class="text-xs tabular-nums text-gray-500">{{ $registro->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-900">
                            <span class="font-medium">{{ $registro->usuario->nombre }}</span>: {{ $registro->descripcion }}
                        </p>
                        @if ($registro->motivo)
                            <p class="text-sm text-gray-600">Motivo: {{ $registro->motivo }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
            <div class="mt-3">{{ $registros->links() }}</div>
        </x-tarjeta>
    @endif
</div>
