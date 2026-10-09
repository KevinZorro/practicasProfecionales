<div class="space-y-4">

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">{{ $errorDeRegla }}</p>
    @endif

    <x-tarjeta>
        <p class="text-base font-semibold text-gray-900">{{ $solicitud->casoClinico->nombre }}</p>
        <p class="text-sm text-gray-600">
            {{ $solicitud->fecha->format('d/m/Y') }} {{ substr($solicitud->hora_inicio, 0, 5) }}–{{ substr($solicitud->hora_fin, 0, 5) }}
            · {{ $solicitud->docente->nombre }} · {{ $solicitud->materia->nombre }}
        </p>
        @if ($solicitud->tieneFormatoIntramural())
            <p class="mt-2 text-sm text-gray-600">
                Registrado el {{ $solicitud->formato_intramural_at->format('d/m/Y H:i') }} por {{ $solicitud->formatoIntramuralPor?->nombre ?? 'desconocido' }}.
            </p>
        @else
            <p class="mt-2 text-sm text-amber-800">Todavía no tiene formato intramural. Se precargó lo que necesita el escenario: ajústalo al formato en papel.</p>
        @endif
    </x-tarjeta>

    @if ($faltantes !== [])
        <div class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20" role="status">
            <p class="font-medium">Quedó guardado, pero no alcanza el inventario en esa franja:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($elegidos->whereIn('id', array_keys($faltantes)) as $item)
                    <li>{{ $item->nombre }}: se piden {{ $items[$item->id] }}, quedan libres {{ $faltantes[$item->id] }}.</li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-tarjeta titulo="Insumos, equipos y simuladores">
        @if ($elegidos->isEmpty())
            <x-mensaje-vacio titulo="Sin elementos" descripcion="Agrega lo que trae el formato en papel." />
        @else
            <div class="space-y-2">
                @foreach ($elegidos as $item)
                    <div class="flex items-center gap-2" wire:key="intramural-{{ $item->id }}">
                        <label for="cantidad-{{ $item->id }}" class="min-w-0 flex-1 truncate text-sm text-gray-700">
                            {{ $item->nombre }} <span class="text-gray-500">· {{ $item->tipo->etiqueta() }}</span>
                        </label>
                        <input type="number" min="1" id="cantidad-{{ $item->id }}" wire:model="items.{{ $item->id }}"
                               class="w-20 shrink-0 rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                        <button type="button" wire:click="quitarItem({{ $item->id }})" class="shrink-0 rounded-md px-2 py-1 text-sm text-rose-700 hover:bg-rose-50">Quitar</button>
                    </div>
                    @error('items.'.$item->id) <p class="text-sm text-rose-700">{{ $message }}</p> @enderror
                @endforeach
            </div>
        @endif

        <div class="mt-4 flex flex-wrap items-end gap-2 border-t border-gray-200 pt-4">
            <div class="min-w-0 flex-1">
                <label for="itemAAgregar" class="mb-1 block text-sm font-medium text-gray-700">Agregar otro</label>
                <select wire:model="itemAAgregar" id="itemAAgregar" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Elige un elemento</option>
                    @foreach ($catalogo as $item)
                        <option value="{{ $item->id }}">{{ $item->nombre }} · {{ $item->tipo->etiqueta() }}</option>
                    @endforeach
                </select>
            </div>
            <x-boton variante="secundario" type="button" wire:click="agregarItem">Agregar</x-boton>
        </div>

        <x-slot:pie>
            <x-boton type="button" wire:click="guardar" wire:loading.attr="disabled" class="px-4 py-2.5">Guardar formato intramural</x-boton>
        </x-slot:pie>
    </x-tarjeta>
</div>
