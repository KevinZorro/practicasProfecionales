<div class="space-y-6">

    {{-- 1 · Escenario y horario --}}
    <x-tarjeta titulo="1 · Escenario y horario">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="tipo" class="mb-1 block text-sm font-medium text-gray-700">Tipo de sesión</label>
                <select wire:model="tipo" id="tipo" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    @foreach ($tiposDeSesion as $unTipo)
                        <option value="{{ $unTipo->value }}">{{ $unTipo->etiqueta() }}</option>
                    @endforeach
                </select>
                @error('tipo') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="materiaId" class="mb-1 block text-sm font-medium text-gray-700">Materia</label>
                <select wire:model="materiaId" id="materiaId" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Elige una materia</option>
                    @foreach ($materias as $materia)
                        <option value="{{ $materia->id }}">{{ $materia->nombre }} · semestre {{ $materia->semestre }}</option>
                    @endforeach
                </select>
                @error('materiaId') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="casoClinicoId" class="mb-1 block text-sm font-medium text-gray-700">Escenario clínico</label>
                <select wire:model.live="casoClinicoId" id="casoClinicoId" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Elige un escenario</option>
                    @foreach ($casosClinicos as $caso)
                        <option value="{{ $caso->id }}">{{ $caso->nombre }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Al elegirlo se precargan los equipos que ese escenario necesita.</p>
                @error('casoClinicoId') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="fecha" class="mb-1 block text-sm font-medium text-gray-700">Fecha</label>
                <input type="date" wire:model="fecha" id="fecha" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                @error('fecha') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="grupo" class="mb-1 block text-sm font-medium text-gray-700">Grupo</label>
                <input type="text" wire:model="grupo" id="grupo" maxlength="2" placeholder="A" autocapitalize="characters"
                       class="w-full rounded-md border border-gray-300 text-sm uppercase focus:border-sky-600 focus:ring-sky-600">
                <p class="mt-1 text-xs text-gray-500">La parte de la clase que pasa a los simuladores en esta sesión.</p>
                @error('grupo') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="horaInicio" class="mb-1 block text-sm font-medium text-gray-700">Hora de inicio</label>
                <input type="time" wire:model="horaInicio" id="horaInicio" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                @error('horaInicio') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="horaFin" class="mb-1 block text-sm font-medium text-gray-700">Hora de fin</label>
                <input type="time" wire:model="horaFin" id="horaFin" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                @error('horaFin') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
        </div>

        <x-slot:pie>
            <span class="font-medium text-gray-700">La sala no se elige aquí.</span>
            La asigna el personal del laboratorio al preparar el escenario, poco antes de la clase, y la verás en tu historial cuando esté decidida.
        </x-slot:pie>
    </x-tarjeta>

    {{-- 2 · Estudiantes --}}
    <x-tarjeta titulo="2 · Estudiantes que van a la sesión">
        <p class="mb-3 text-sm text-gray-700">
            {{ count($estudianteIds) }} {{ count($estudianteIds) === 1 ? 'estudiante' : 'estudiantes' }}
            @if ($capacidadDelCaso !== null)
                · este escenario admite {{ $capacidadDelCaso }} como máximo
            @endif
        </p>

        @error('estudianteIds') <p class="mb-3 text-sm text-rose-700" role="alert">{{ $message }}</p> @enderror

        @if ($seleccionados->isNotEmpty())
            <ul class="mb-4 divide-y divide-gray-200 rounded-md border border-gray-200">
                @foreach ($seleccionados as $estudiante)
                    <li class="flex items-center justify-between gap-2 px-3 py-2" wire:key="estudiante-{{ $estudiante->id }}">
                        <span class="min-w-0 truncate text-sm text-gray-900">
                            {{ $estudiante->nombre }}
                            @if ($estudiante->codigo_institucional)
                                <span class="text-gray-500">· {{ $estudiante->codigo_institucional }}</span>
                            @endif
                        </span>
                        <button type="button" wire:click="quitarEstudiante({{ $estudiante->id }})"
                                class="shrink-0 rounded-md px-2 py-1 text-sm text-rose-700 hover:bg-rose-50">
                            Quitar
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        <label for="busquedaEstudiante" class="mb-1 block text-sm font-medium text-gray-700">Buscar por nombre o código</label>
        <input type="search" wire:model.live.debounce.400ms="busquedaEstudiante" id="busquedaEstudiante" autocomplete="off"
               class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">

        @if ($resultados->isNotEmpty())
            <ul class="mt-2 divide-y divide-gray-200 rounded-md border border-gray-200">
                @foreach ($resultados as $estudiante)
                    <li wire:key="resultado-{{ $estudiante->id }}">
                        <button type="button" wire:click="agregarEstudiante({{ $estudiante->id }})"
                                class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-sm hover:bg-sky-50">
                            <span class="min-w-0 truncate">{{ $estudiante->nombre }} <span class="text-gray-500">· {{ $estudiante->codigo_institucional ?? $estudiante->email }}</span></span>
                            <span class="shrink-0 font-medium text-sky-800">Agregar</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @elseif (trim($busquedaEstudiante) !== '')
            <p class="mt-2 text-sm text-gray-600">Ningún estudiante activo coincide con «{{ $busquedaEstudiante }}».</p>
        @endif

        <div class="mt-4 border-t border-gray-200 pt-4">
            <label for="codigosPegados" class="mb-1 block text-sm font-medium text-gray-700">O pega los códigos del grupo</label>
            <textarea wire:model="codigosPegados" id="codigosPegados" rows="2"
                      class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600"
                      placeholder="Separados por comas, espacios o uno por línea"></textarea>
            <x-boton variante="secundario" type="button" wire:click="agregarPorCodigo" class="mt-2">Agregar los códigos</x-boton>

            @if ($codigosDesconocidos !== [])
                <p class="mt-2 text-sm text-amber-800">
                    Estos códigos no son de ningún estudiante activo: {{ implode(', ', $codigosDesconocidos) }}.
                </p>
            @endif
        </div>
    </x-tarjeta>

    {{-- 3 · Equipos --}}
    <x-tarjeta titulo="3 · Equipos a solicitar">
        <x-lista-equipos :items="collect($seleccionadosPorTipo)->flatten()" :cantidades="$items">
        </x-lista-equipos>

        @if ($items !== [])
            <div class="mt-4 space-y-2">
                @foreach ($items as $itemId => $cantidad)
                    <div class="flex items-center gap-2" wire:key="cantidad-{{ $itemId }}">
                        <label for="cantidad-{{ $itemId }}" class="min-w-0 flex-1 truncate text-sm text-gray-700">
                            {{ collect($seleccionadosPorTipo)->flatten()->firstWhere('id', $itemId)?->nombre }}
                        </label>
                        <input type="number" min="1" id="cantidad-{{ $itemId }}"
                               wire:model.live.debounce.400ms="items.{{ $itemId }}"
                               class="w-20 shrink-0 rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                        <button type="button" wire:click="quitarItem({{ $itemId }})"
                                class="shrink-0 rounded-md px-2 py-1 text-sm text-rose-700 hover:bg-rose-50">
                            Quitar
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-4 flex flex-wrap items-end gap-2 border-t border-gray-200 pt-4">
            <div class="min-w-0 flex-1">
                <label for="itemAAgregar" class="mb-1 block text-sm font-medium text-gray-700">Agregar otro equipo</label>
                <select wire:model="itemAAgregar" id="itemAAgregar" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Elige un equipo</option>
                    @foreach ($catalogo as $item)
                        <option value="{{ $item->id }}">{{ $item->nombre }} · {{ $item->tipo->etiqueta() }}</option>
                    @endforeach
                </select>
            </div>
            <x-boton variante="secundario" type="button" wire:click="agregarItem">Agregar</x-boton>
        </div>
    </x-tarjeta>

    {{-- 4 · Observaciones --}}
    <x-tarjeta titulo="4 · Observaciones para el laboratorio">
        <textarea wire:model="observaciones" rows="3"
                  class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600"
                  placeholder="Cualquier detalle que el laboratorio deba tener en cuenta."></textarea>
        @error('observaciones') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
    </x-tarjeta>

    <div class="flex flex-wrap gap-2">
        <x-boton wire:click="guardar" wire:loading.attr="disabled">Enviar solicitud</x-boton>
        <x-boton variante="secundario" href="{{ route('panel.mis-solicitudes') }}">Cancelar</x-boton>
    </div>
</div>
