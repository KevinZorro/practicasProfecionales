<div class="space-y-5">

    <x-tarjeta titulo="Consultar disponibilidad">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="itemId" class="campo-etiqueta">Ítem</label>
                <select wire:model="itemId" id="itemId"
                        class="campo">
                    <option value="">Elige un ítem</option>
                    @foreach ($items as $item)
                        <option value="{{ $item->id }}">{{ $item->nombre }} · {{ $item->tipo->etiqueta() }}</option>
                    @endforeach
                </select>
                @error('itemId') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="fecha" class="campo-etiqueta">Fecha</label>
                <input type="date" wire:model="fecha" id="fecha"
                       class="campo">
                @error('fecha') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="horaInicio" class="campo-etiqueta">Desde</label>
                    <input type="time" wire:model="horaInicio" id="horaInicio"
                           class="campo">
                </div>
                <div>
                    <label for="horaFin" class="campo-etiqueta">Hasta</label>
                    <input type="time" wire:model="horaFin" id="horaFin"
                           class="campo">
                </div>
                @error('horaInicio') <p class="campo-error col-span-2 -mt-1">{{ $message }}</p> @enderror
                @error('horaFin') <p class="campo-error col-span-2 -mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <x-slot:pie>
            <x-boton type="button" wire:click="consultar" wire:loading.attr="disabled" wire:target="consultar">Consultar</x-boton>
        </x-slot:pie>
    </x-tarjeta>

    @if ($libres !== null && $consultado !== null)
        <x-tarjeta>
            <p class="text-[15px] font-semibold text-gray-900">{{ $consultado->nombre }}</p>
            <p class="mt-2 flex flex-wrap items-baseline gap-x-2">
                <span class="text-[2.5rem] font-extrabold leading-none tracking-[-0.03em] {{ $libres === 0 ? 'text-rojo' : 'text-gray-900' }}">{{ $libres }}</span>
                <span class="text-[15px] text-gray-600">
                    {{ trans_choice('unidad libre|unidades libres', $libres) }} de {{ $consultado->cantidad_total }}
                </span>
            </p>
            <p class="mt-3 text-[14px] leading-snug text-gray-500">
                El {{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') }}, de {{ $horaInicio }} a {{ $horaFin }}.
                Se descuenta lo comprometido en solicitudes ya aprobadas que se pisen con esa franja.
            </p>
        </x-tarjeta>
    @endif
</div>
