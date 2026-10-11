<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">{{ $errorDeRegla }}</x-aviso>
    @endif

    @if ($solicitud->tieneFormatoIntramural())
        <x-aviso tono="exito">
            Registrado el {{ $solicitud->formato_intramural_at->format('d/m/Y H:i') }} por {{ $solicitud->formatoIntramuralPor?->nombre ?? 'desconocido' }}.
            Si llega una corrección en papel, ajústalo y vuelve a guardarlo.
        </x-aviso>
    @else
        <x-aviso tono="atencion">
            Todavía no tiene formato intramural. Se precargó lo que necesita el escenario: ajústalo al formato en papel.
        </x-aviso>
    @endif

    @if ($faltantes !== [])
        <x-aviso tono="atencion" role="status">
            <p class="font-semibold">Quedó guardado, pero no alcanza el inventario en esa franja:</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">
                @foreach ($elegidos->whereIn('id', array_keys($faltantes)) as $item)
                    <li>{{ $item->nombre }}: se piden {{ $items[$item->id] }}, quedan libres {{ $faltantes[$item->id] }}.</li>
                @endforeach
            </ul>
        </x-aviso>
    @endif

    <x-tarjeta titulo="Insumos, equipos y simuladores" descripcion="Lo que trae el formato en papel, con sus cantidades.">
        @if ($elegidos->isEmpty())
            <p class="rounded-xl bg-gray-50 px-4 py-6 text-center text-[14px] text-gray-500">Sin elementos. Agrega lo que trae el formato en papel.</p>
        @else
            <ul class="divide-y divide-gray-100 border-y border-gray-100">
                @foreach ($elegidos as $item)
                    <li class="py-2.5" wire:key="intramural-{{ $item->id }}">
                        <div class="flex items-center gap-3">
                            <label for="cantidad-{{ $item->id }}" class="min-w-0 flex-1 text-[15px] leading-snug text-gray-900">
                                {{ $item->nombre }} <span class="text-[13px] text-gray-500">· {{ $item->tipo->etiqueta() }}</span>
                            </label>
                            <input type="number" min="1" inputmode="numeric" id="cantidad-{{ $item->id }}" wire:model="items.{{ $item->id }}"
                                   class="campo w-20 shrink-0 text-center tabular-nums">
                            <x-boton variante="texto" tamano="chico" type="button" wire:click="quitarItem({{ $item->id }})" aria-label="Quitar {{ $item->nombre }}">Quitar</x-boton>
                        </div>
                        @error('items.'.$item->id) <p class="campo-error">{{ $message }}</p> @enderror
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-5 flex flex-wrap items-end gap-2">
            <div class="min-w-0 flex-1 basis-56">
                <label for="itemAAgregar" class="campo-etiqueta">Agregar otro</label>
                <select wire:model="itemAAgregar" id="itemAAgregar" class="campo">
                    <option value="">Elige un elemento</option>
                    @foreach ($catalogo as $item)
                        <option value="{{ $item->id }}">{{ $item->nombre }} · {{ $item->tipo->etiqueta() }}</option>
                    @endforeach
                </select>
            </div>
            <x-boton variante="secundario" type="button" wire:click="agregarItem">Agregar</x-boton>
        </div>

        <x-slot:pie>
            <x-boton type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">Guardar formato intramural</x-boton>
        </x-slot:pie>
    </x-tarjeta>
</div>
