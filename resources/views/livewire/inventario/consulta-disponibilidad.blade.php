<div class="space-y-4">

    <x-tarjeta titulo="Consultar disponibilidad">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="itemId" class="mb-1 block text-sm font-medium text-gray-700">Ítem</label>
                <select wire:model="itemId" id="itemId"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Elige un ítem</option>
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}">{{ $item->nombre }} · {{ $item->tipo->etiqueta() }}</option>
                    @endforeach
                </select>
                @error('itemId') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="fecha" class="mb-1 block text-sm font-medium text-gray-700">Fecha</label>
                <input type="date" wire:model="fecha" id="fecha"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                @error('fecha') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="horaInicio" class="mb-1 block text-sm font-medium text-gray-700">Desde</label>
                    <input type="time" wire:model="horaInicio" id="horaInicio"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                </div>
                <div>
                    <label for="horaFin" class="mb-1 block text-sm font-medium text-gray-700">Hasta</label>
                    <input type="time" wire:model="horaFin" id="horaFin"
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                </div>
                @error('horaInicio') <p class="col-span-2 mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                @error('horaFin') <p class="col-span-2 mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
        </div>

        <x-slot:pie>
            <x-boton wire:click="consultar" class="px-4 py-2.5">Consultar</x-boton>
        </x-slot:pie>
    </x-tarjeta>

    @if ($libres !== null && $consultado !== null)
        <x-tarjeta>
            <p class="text-sm text-gray-600">{{ $consultado->nombre }}</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums {{ $libres === 0 ? 'text-rose-700' : 'text-gray-900' }}">
                {{ $libres }}
                <span class="text-base font-normal text-gray-600">
                    {{ trans_choice('unidad libre|unidades libres', $libres) }} de {{ $consultado->cantidad_total }}
                </span>
            </p>
            <p class="mt-2 text-sm text-gray-600">
                El {{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') }}, de {{ $horaInicio }} a {{ $horaFin }}.
                Se descuenta lo comprometido en solicitudes ya aprobadas que se pisen con esa franja.
            </p>
        </x-tarjeta>
    @endif
</div>
