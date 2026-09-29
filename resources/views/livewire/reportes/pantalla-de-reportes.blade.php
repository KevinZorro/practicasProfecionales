<div class="space-y-4">

    <x-tarjeta>
        <div>
            <label for="reporte" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Reporte</label>
            <select wire:model.live="reporte" id="reporte"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                @foreach ($reportes as $opcion)
                    <option value="{{ $opcion->value }}">{{ $opcion->titulo() }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-sm text-gray-600">{{ $elegido->descripcion() }}</p>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="desde" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Desde</label>
                <input type="date" wire:model.live="desde" id="desde"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                @error('desde') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="hasta" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Hasta</label>
                <input type="date" wire:model.live="hasta" id="hasta"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                @error('hasta') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="docente" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Docente</label>
                <select wire:model.live="docente" id="docente"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Todos</option>
                    @foreach ($docentes as $unDocente)
                        <option value="{{ $unDocente->id }}">{{ $unDocente->nombre }}</option>
                    @endforeach
                </select>
                @error('docente') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="materia" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Materia</label>
                <select wire:model.live="materia" id="materia"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Todas</option>
                    @foreach ($materias as $unaMateria)
                        <option value="{{ $unaMateria->id }}">{{ $unaMateria->nombre }}{{ $unaMateria->activo ? '' : ' (inactiva)' }}</option>
                    @endforeach
                </select>
                @error('materia') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            @if ($salas !== null)
                <div>
                    <label for="sala" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Sala</label>
                    <select wire:model.live="sala" id="sala"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                        <option value="">Todas</option>
                        @foreach ($salas as $unaSala)
                            <option value="{{ $unaSala->id }}">{{ $unaSala->nombre }}{{ $unaSala->activo ? '' : ' (inactiva)' }}</option>
                        @endforeach
                    </select>
                    @error('sala') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            @if ($consultaDeDescarga !== null)
                {{-- :href y no href="{{ }}": el componente ya escapa, y escapar dos
                     veces convierte los & de la consulta en &amp;amp;, con lo que
                     se perderían todos los filtros menos el primero. --}}
                <x-boton :href="route('panel.reportes.excel', ['reporte' => $elegido, ...$consultaDeDescarga])">Descargar Excel</x-boton>
                <x-boton variante="secundario" :href="route('panel.reportes.pdf', ['reporte' => $elegido, ...$consultaDeDescarga])">Descargar PDF</x-boton>
            @endif
            <x-boton variante="secundario" tipo="button" wire:click="limpiarFiltros">Quitar filtros</x-boton>
        </div>
    </x-tarjeta>

    @if ($filas === null)
        <x-mensaje-vacio
            titulo="Corrige los filtros para ver el reporte"
            descripcion="Mientras haya un filtro inválido no se consulta ni se descarga nada."
        />
    @elseif ($filas->isEmpty())
        <x-mensaje-vacio
            titulo="No hay registros para estos filtros"
            descripcion="Prueba con un rango de fechas más amplio o quita algún filtro."
        />
    @else
        <p class="text-sm text-gray-600">{{ $filas->total() }} {{ $filas->total() === 1 ? 'registro' : 'registros' }}</p>

        <x-tabla :encabezados="$encabezados">
            @foreach ($filas as $fila)
                <tr wire:key="fila-{{ $filas->currentPage() }}-{{ $loop->index }}">
                    @foreach ($fila as $celda)
                        <td class="whitespace-nowrap px-4 py-3 text-gray-900">{{ $celda }}</td>
                    @endforeach
                </tr>
            @endforeach
        </x-tabla>

        {{ $filas->links() }}
    @endif
</div>
