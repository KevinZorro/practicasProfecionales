<div class="space-y-5">

    {{-- Un docente bloqueado no pide escenarios (RF68): se le dice antes de que llene nada. --}}
    @if ($bloqueo)
        <x-aviso tono="peligro" role="alert">
            <p class="font-semibold">No puedes solicitar escenarios mientras tengas un bloqueo vigente.</p>
            <p class="mt-0.5">Motivo: {{ $bloqueo->motivo }}. Consulta con la coordinación del laboratorio.</p>
        </x-aviso>
    @endif

    {{-- 1 · Escenario y horario --}}
    <x-tarjeta titulo="1 · Escenario y horario">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="tipo" class="campo-etiqueta">Tipo de sesión</label>
                <select wire:model="tipo" id="tipo" class="campo">
                    @foreach ($tiposDeSesion as $unTipo)
                        <option value="{{ $unTipo->value }}">{{ $unTipo->etiqueta() }}</option>
                    @endforeach
                </select>
                @error('tipo') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="materiaId" class="campo-etiqueta">Materia</label>
                <select wire:model="materiaId" id="materiaId" class="campo">
                    <option value="">Elige una materia</option>
                    @foreach ($materias as $materia)
                        <option value="{{ $materia->id }}">{{ $materia->nombre }} · semestre {{ $materia->semestre }}</option>
                    @endforeach
                </select>
                @error('materiaId') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="casoClinicoId" class="campo-etiqueta">Escenario clínico</label>
                <select wire:model.live="casoClinicoId" id="casoClinicoId" class="campo">
                    <option value="">Elige un escenario</option>
                    @foreach ($casosClinicos as $caso)
                        <option value="{{ $caso->id }}">{{ $caso->nombre }}</option>
                    @endforeach
                </select>
                <p class="campo-ayuda">Al elegirlo se precargan los equipos que ese escenario necesita.</p>
                @error('casoClinicoId') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="fecha" class="campo-etiqueta">Fecha</label>
                <input type="date" wire:model="fecha" id="fecha" class="campo">
                @error('fecha') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="grupo" class="campo-etiqueta">Grupo</label>
                <input type="text" wire:model="grupo" id="grupo" maxlength="2" placeholder="A" autocapitalize="characters"
                       class="campo uppercase">
                <p class="campo-ayuda">La parte de la clase que pasa a los simuladores en esta sesión.</p>
                @error('grupo') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="horaInicio" class="campo-etiqueta">Hora de inicio</label>
                <input type="time" wire:model="horaInicio" id="horaInicio" class="campo">
                @error('horaInicio') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="horaFin" class="campo-etiqueta">Hora de fin</label>
                <input type="time" wire:model="horaFin" id="horaFin" class="campo">
                @error('horaFin') <p class="campo-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <x-slot:pie>
            <p>
                <span class="font-semibold text-gray-800">La sala no se elige aquí.</span>
                La asigna el personal del laboratorio al preparar el escenario, poco antes de la clase, y la verás en tu historial cuando esté decidida.
            </p>
        </x-slot:pie>
    </x-tarjeta>

    {{-- 2 · Estudiantes --}}
    <x-tarjeta titulo="2 · Estudiantes que van a la sesión">
        <p class="mb-3 text-[14px] text-gray-700">
            <span class="font-semibold text-gray-900">{{ count($estudianteIds) }} {{ count($estudianteIds) === 1 ? 'estudiante' : 'estudiantes' }}</span>
            @if ($capacidadDelCaso !== null)
                · este escenario admite {{ $capacidadDelCaso }} como máximo
            @endif
        </p>

        @error('estudianteIds') <x-aviso tono="peligro" role="alert" class="mb-3">{{ $message }}</x-aviso> @enderror

        @if ($seleccionados->isNotEmpty())
            <ul class="mb-5 divide-y divide-gray-100 rounded-xl bg-gray-50">
                @foreach ($seleccionados as $estudiante)
                    <li class="flex items-center justify-between gap-3 py-1.5 pl-3.5 pr-1.5" wire:key="estudiante-{{ $estudiante->id }}">
                        <span class="min-w-0 truncate text-[14px] text-gray-900">
                            {{ $estudiante->nombre }}
                            @if ($estudiante->codigo_institucional)
                                <span class="text-gray-500">· {{ $estudiante->codigo_institucional }}</span>
                            @endif
                        </span>
                        <x-boton variante="texto" tamano="chico" type="button" wire:click="quitarEstudiante({{ $estudiante->id }})" aria-label="Quitar a {{ $estudiante->nombre }}">
                            Quitar
                        </x-boton>
                    </li>
                @endforeach
            </ul>
        @endif

        <label for="busquedaEstudiante" class="campo-etiqueta">Buscar por nombre o código</label>
        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.2-5.2M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/>
            </svg>
            <input type="search" wire:model.live.debounce.400ms="busquedaEstudiante" id="busquedaEstudiante" autocomplete="off" class="campo pl-9">
        </div>

        @if ($resultados->isNotEmpty())
            <ul class="mt-2 divide-y divide-gray-100 overflow-hidden rounded-xl ring-1 ring-gray-300">
                @foreach ($resultados as $estudiante)
                    <li wire:key="resultado-{{ $estudiante->id }}">
                        <button type="button" wire:click="agregarEstudiante({{ $estudiante->id }})"
                                class="flex min-h-11 w-full items-center justify-between gap-3 px-3.5 py-2 text-left text-[14px] transition-colors duration-150 hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none">
                            <span class="min-w-0 truncate">{{ $estudiante->nombre }} <span class="text-gray-500">· {{ $estudiante->codigo_institucional ?? $estudiante->email }}</span></span>
                            <span class="shrink-0 font-semibold text-gray-900">Agregar</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @elseif (trim($busquedaEstudiante) !== '')
            <p class="campo-ayuda">Ningún estudiante activo coincide con «{{ $busquedaEstudiante }}».</p>
        @endif

        <div class="mt-5 border-t border-gray-100 pt-5">
            <label for="codigosPegados" class="campo-etiqueta">O pega los códigos del grupo</label>
            <textarea wire:model="codigosPegados" id="codigosPegados" rows="2" class="campo"
                      placeholder="Separados por comas, espacios o uno por línea"></textarea>
            <x-boton variante="secundario" tamano="chico" type="button" wire:click="agregarPorCodigo" class="mt-2">Agregar los códigos</x-boton>

            @if ($codigosDesconocidos !== [])
                <x-aviso tono="atencion" class="mt-3">
                    Estos códigos no son de ningún estudiante activo: {{ implode(', ', $codigosDesconocidos) }}.
                </x-aviso>
            @endif
        </div>
    </x-tarjeta>

    {{-- 3 · Equipos: una sola lista, por tipo, con la cantidad a la mano. Sin existencias (RF40). --}}
    <x-tarjeta titulo="3 · Equipos a solicitar" descripcion="Se precargan los que necesita el escenario. Ajusta las cantidades o agrega otros.">
        @if ($items === [])
            <p class="rounded-xl bg-gray-50 px-4 py-6 text-center text-[14px] text-gray-500">Elige un escenario para precargar sus equipos, o agrégalos aquí abajo.</p>
        @else
            <div class="space-y-4">
                @foreach ($seleccionadosPorTipo as $tipo => $delTipo)
                    <div>
                        <h3 class="mb-2 text-[13px] font-semibold text-gray-500">{{ \App\Enums\TipoItemInventario::from($tipo)->etiqueta() }}</h3>
                        <ul class="divide-y divide-gray-100 rounded-xl bg-gray-50">
                            @foreach ($delTipo as $item)
                                <li class="py-1.5 pl-3.5 pr-1.5" wire:key="cantidad-{{ $item->id }}">
                                    <div class="flex items-center gap-2">
                                        <label for="cantidad-{{ $item->id }}" class="min-w-0 flex-1 text-[14px] leading-snug text-gray-900">{{ $item->nombre }}</label>
                                        <input type="number" min="1" inputmode="numeric" id="cantidad-{{ $item->id }}"
                                               wire:model.live.debounce.400ms="items.{{ $item->id }}"
                                               class="campo w-[4.5rem] shrink-0 py-1.5 text-center sm:py-1.5">
                                        <x-boton variante="texto" tamano="chico" type="button" wire:click="quitarItem({{ $item->id }})" aria-label="Quitar {{ $item->nombre }}">
                                            Quitar
                                        </x-boton>
                                    </div>
                                    @error('items.'.$item->id) <p class="campo-error">{{ $message }}</p> @enderror
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-5 flex flex-wrap items-end gap-2">
            <div class="min-w-0 flex-1 basis-56">
                <label for="itemAAgregar" class="campo-etiqueta">Agregar otro equipo</label>
                <select wire:model="itemAAgregar" id="itemAAgregar" class="campo">
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
        <label for="observaciones" class="sr-only">Observaciones para el laboratorio</label>
        <textarea wire:model="observaciones" id="observaciones" rows="3" class="campo"
                  placeholder="Cualquier detalle que el laboratorio deba tener en cuenta."></textarea>
        @error('observaciones') <p class="campo-error">{{ $message }}</p> @enderror
    </x-tarjeta>

    <div class="flex flex-wrap items-center gap-2">
        @unless ($bloqueo)
            <x-boton type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">Enviar solicitud</x-boton>
        @endunless
        <x-boton variante="texto" href="{{ route('panel.mis-solicitudes') }}">Cancelar</x-boton>
        @if ($errors->isNotEmpty())
            <p class="text-[14px] font-medium text-rojo-700" role="alert">Revisa los campos marcados.</p>
        @endif
    </div>
</div>
