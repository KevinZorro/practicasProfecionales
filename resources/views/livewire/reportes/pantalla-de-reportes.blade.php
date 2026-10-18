<div class="space-y-5">

    <div>
        <x-filtro-pastillas leyenda="Reporte" nombre="reporte" wire:model.live="reporte"
                            :opciones="collect($reportes)->mapWithKeys(fn ($opcion) => [$opcion->value => $opcion->titulo()])->all()" />
        <p class="mt-2.5 max-w-[70ch] text-[14px] leading-snug text-gray-500">{{ $elegido->descripcion() }}</p>
    </div>

    <x-tarjeta>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="desde" class="campo-etiqueta">Desde</label>
                <input type="date" wire:model.live="desde" id="desde"
                       class="campo">
                @error('desde') <p class="campo-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="hasta" class="campo-etiqueta">Hasta</label>
                <input type="date" wire:model.live="hasta" id="hasta"
                       class="campo">
                @error('hasta') <p class="campo-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="docente" class="campo-etiqueta">Docente</label>
                <select wire:model.live="docente" id="docente"
                        class="campo">
                    <option value="">Todos</option>
                    @foreach ($docentes as $unDocente)
                        <option value="{{ $unDocente->id }}">{{ $unDocente->nombre }}</option>
                    @endforeach
                </select>
                @error('docente') <p class="campo-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="materia" class="campo-etiqueta">Materia</label>
                <select wire:model.live="materia" id="materia"
                        class="campo">
                    <option value="">Todas</option>
                    @foreach ($materias as $unaMateria)
                        <option value="{{ $unaMateria->id }}">{{ $unaMateria->nombre }}{{ $unaMateria->activo ? '' : ' (inactiva)' }}</option>
                    @endforeach
                </select>
                @error('materia') <p class="campo-error">{{ $message }}</p> @enderror
            </div>
            @if ($salas !== null)
                <div>
                    <label for="sala" class="campo-etiqueta">Sala</label>
                    <select wire:model.live="sala" id="sala"
                            class="campo">
                        <option value="">Todas</option>
                        @foreach ($salas as $unaSala)
                            <option value="{{ $unaSala->id }}">{{ $unaSala->nombre }}{{ $unaSala->activo ? '' : ' (inactiva)' }}</option>
                        @endforeach
                    </select>
                    @error('sala') <p class="campo-error">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-2">
            @if ($consultaDeDescarga !== null)
                {{-- :href y no href="{{ }}": el componente ya escapa, y escapar dos
                     veces convierte los & de la consulta en &amp;amp;, con lo que
                     se perderían todos los filtros menos el primero. --}}
                <x-boton :href="route('panel.reportes.excel', ['reporte' => $elegido, ...$consultaDeDescarga])">Descargar Excel</x-boton>
                @if ($motivoSinPdf === null)
                    <x-boton variante="secundario" :href="route('panel.reportes.pdf', ['reporte' => $elegido, ...$consultaDeDescarga])">Descargar PDF</x-boton>
                @endif
            @endif
            <x-boton variante="texto" tipo="button" wire:click="limpiarFiltros">Quitar filtros</x-boton>
        </div>
        @if ($consultaDeDescarga !== null && $motivoSinPdf !== null)
            <p class="mt-2 text-[14px] text-gray-600">{{ $motivoSinPdf }}</p>
        @endif
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
        <p class="text-[14px] text-gray-500">{{ $filas->total() }} {{ $filas->total() === 1 ? 'registro' : 'registros' }}</p>

        <x-tabla :encabezados="$encabezados">
            @foreach ($filas as $fila)
                <tr wire:key="fila-{{ $filas->currentPage() }}-{{ $loop->index }}">
                    @foreach ($fila as $celda)
                        <td class="whitespace-nowrap text-gray-900">{{ $celda }}</td>
                    @endforeach
                </tr>
            @endforeach
        </x-tabla>

        {{ $filas->links() }}
    @endif
</div>
