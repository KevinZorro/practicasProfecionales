<div class="space-y-5">
    <x-tarjeta>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="accion" class="campo-etiqueta">Acción</label>
                <select wire:model.live="accion" id="accion" class="campo">
                    <option value="">Todas</option>
                    @foreach ($acciones as $unaAccion)
                        <option value="{{ $unaAccion->value }}">{{ $unaAccion->etiqueta() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="persona" class="campo-etiqueta">Quién</label>
                <input type="search" wire:model.live.debounce.400ms="persona" id="persona" placeholder="Nombre"
                       class="campo">
            </div>
            <div>
                <label for="desde" class="campo-etiqueta">Desde</label>
                <input type="date" wire:model.live="desde" id="desde" class="campo">
                @error('desde') <p class="campo-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="hasta" class="campo-etiqueta">Hasta</label>
                <input type="date" wire:model.live="hasta" id="hasta" class="campo">
                @error('hasta') <p class="campo-error">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
            <p class="text-[14px] text-gray-500">{{ trans_choice(':count registro|:count registros', $registros->total(), ['count' => $registros->total()]) }}</p>
            <x-boton variante="texto" tamano="chico" type="button" wire:click="limpiarFiltros">Limpiar filtros</x-boton>
        </div>
    </x-tarjeta>

    @if ($registros->isEmpty())
        <x-mensaje-vacio titulo="No hay registros" descripcion="Aquí quedan las aprobaciones, rechazos, reprogramaciones, sustituciones, retiros, bloqueos y cambios de rol." />
    @else
        <ul class="divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white">
            @foreach ($registros as $registro)
                <li wire:key="bitacora-{{ $registro->id }}" class="px-4 py-3.5 sm:px-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <x-insignia>{{ $registro->accion->etiqueta() }}</x-insignia>
                        <span class="text-[13px] text-gray-500">{{ $registro->created_at?->format('d/m/Y H:i') }}</span>
                    </div>
                    <p class="mt-1.5 text-[15px] leading-snug text-gray-900">
                        <span class="font-semibold">{{ $registro->usuario->is($yo) ? 'Yo' : $registro->usuario->nombre }}</span>: {{ $registro->descripcion }}
                    </p>
                    @if ($registro->motivo)
                        <p class="mt-0.5 text-[14px] leading-snug text-gray-600">Motivo: {{ $registro->motivo }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
        <div>{{ $registros->links() }}</div>
    @endif
</div>
