<div class="space-y-4">

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">{{ $errorDeRegla }}</p>
    @endif

    <x-tarjeta titulo="Registrar una sesión apartada">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="docenteId" class="mb-1 block text-sm font-medium text-gray-700">Docente</label>
                <select wire:model="docenteId" id="docenteId" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Elige un docente</option>
                    @foreach ($docentes as $docente)
                        <option value="{{ $docente->id }}">{{ $docente->nombre }}</option>
                    @endforeach
                </select>
                @error('docenteId') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
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

            <div>
                <label for="casoClinicoId" class="mb-1 block text-sm font-medium text-gray-700">Escenario clínico</label>
                <select wire:model="casoClinicoId" id="casoClinicoId" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Elige un escenario</option>
                    @foreach ($casosClinicos as $caso)
                        <option value="{{ $caso->id }}">{{ $caso->nombre }}</option>
                    @endforeach
                </select>
                @error('casoClinicoId') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="tipo" class="mb-1 block text-sm font-medium text-gray-700">Tipo de sesión</label>
                <select wire:model="tipo" id="tipo" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    @foreach ($tiposDeSesion as $unTipo)
                        <option value="{{ $unTipo->value }}">{{ $unTipo->etiqueta() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="cantidadEstudiantes" class="mb-1 block text-sm font-medium text-gray-700">Estudiantes</label>
                    <input type="number" min="1" wire:model="cantidadEstudiantes" id="cantidadEstudiantes"
                           class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    @error('cantidadEstudiantes') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="grupo" class="mb-1 block text-sm font-medium text-gray-700">Grupo <span class="font-normal text-gray-500">(si viene)</span></label>
                    <input type="text" wire:model="grupo" id="grupo" maxlength="2" placeholder="A"
                           class="w-full rounded-md border border-gray-300 text-sm uppercase focus:border-sky-600 focus:ring-sky-600">
                    @error('grupo') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="fecha" class="mb-1 block text-sm font-medium text-gray-700">Fecha</label>
                <input type="date" wire:model.live="fecha" id="fecha" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                @error('fecha') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label for="horaInicio" class="mb-1 block text-sm font-medium text-gray-700">Inicio</label>
                    <input type="time" wire:model.live="horaInicio" id="horaInicio" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    @error('horaInicio') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="horaFin" class="mb-1 block text-sm font-medium text-gray-700">Fin</label>
                    <input type="time" wire:model.live="horaFin" id="horaFin" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    @error('horaFin') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        @if ($advertencias !== [])
            <div class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20" role="status">
                <p class="font-medium">Cruces en esa franja (puedes registrarla igual):</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($advertencias as $advertencia)
                        <li>{{ $advertencia }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-slot:pie>
            <div class="flex flex-wrap items-center gap-3">
                <x-boton type="button" wire:click="registrar" wire:loading.attr="disabled" class="px-4 py-2.5">Registrar sesión</x-boton>
                <span>Queda aprobada: la aprobó coordinación al entregar el formato. La sala se asigna al preparar.</span>
            </div>
        </x-slot:pie>
    </x-tarjeta>

    <x-tarjeta titulo="Sesiones apartadas">
        <label class="mb-3 flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" wire:model.live="soloSinFormato" class="rounded border border-gray-300">
            Solo las que no tienen formato intramural
        </label>

        @if ($sesiones->isEmpty())
            <x-mensaje-vacio titulo="No hay sesiones apartadas" descripcion="Las que registres aparecerán aquí." />
        @else
            <ul class="divide-y divide-gray-200">
                @foreach ($sesiones as $sesion)
                    <li wire:key="apartada-{{ $sesion->id }}" class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ $sesion->casoClinico->nombre }}</p>
                            <p class="text-sm text-gray-600">
                                {{ $sesion->fecha->format('d/m/Y') }} {{ substr($sesion->hora_inicio, 0, 5) }}–{{ substr($sesion->hora_fin, 0, 5) }}
                                · {{ $sesion->docente->nombre }} · {{ $sesion->materia->nombre }}{{ $sesion->grupo ? ' · grupo '.$sesion->grupo : '' }}
                            </p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            @if ($sesion->tieneFormatoIntramural())
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-900 ring-1 ring-inset ring-emerald-600/20">Con formato intramural</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-900 ring-1 ring-inset ring-amber-600/20">Sin formato intramural</span>
                            @endif
                            <x-boton variante="secundario" href="{{ route('panel.solicitudes.formato-intramural', $sesion) }}" class="px-3 py-2">
                                {{ $sesion->tieneFormatoIntramural() ? 'Ver insumos' : 'Registrar insumos' }}
                            </x-boton>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="mt-3">{{ $sesiones->links() }}</div>
        @endif
    </x-tarjeta>
</div>
