<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">{{ $errorDeRegla }}</x-aviso>
    @endif

    <x-tarjeta titulo="Registrar una sesión apartada">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="docenteId" class="campo-etiqueta">Docente</label>
                <select wire:model="docenteId" id="docenteId" class="campo">
                    <option value="">Elige un docente</option>
                    @foreach ($docentes as $docente)
                        <option value="{{ $docente->id }}">{{ $docente->nombre }}</option>
                    @endforeach
                </select>
                @error('docenteId') <p class="campo-error">{{ $message }}</p> @enderror
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

            <div>
                <label for="casoClinicoId" class="campo-etiqueta">Escenario clínico</label>
                <select wire:model="casoClinicoId" id="casoClinicoId" class="campo">
                    <option value="">Elige un escenario</option>
                    @foreach ($casosClinicos as $caso)
                        <option value="{{ $caso->id }}">{{ $caso->nombre }}</option>
                    @endforeach
                </select>
                @error('casoClinicoId') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="tipo" class="campo-etiqueta">Tipo de sesión</label>
                <select wire:model="tipo" id="tipo" class="campo">
                    @foreach ($tiposDeSesion as $unTipo)
                        <option value="{{ $unTipo->value }}">{{ $unTipo->etiqueta() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="cantidadEstudiantes" class="campo-etiqueta">Estudiantes</label>
                    <input type="number" min="1" wire:model="cantidadEstudiantes" id="cantidadEstudiantes"
                           class="campo">
                    @error('cantidadEstudiantes') <p class="campo-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="grupo" class="campo-etiqueta">Grupo <span class="font-normal text-gray-500">(si viene)</span></label>
                    <input type="text" wire:model="grupo" id="grupo" maxlength="2" placeholder="A"
                           class="campo uppercase">
                    @error('grupo') <p class="campo-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="fecha" class="campo-etiqueta">Fecha</label>
                <input type="date" wire:model.live="fecha" id="fecha" class="campo">
                @error('fecha') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="horaInicio" class="campo-etiqueta">Inicio</label>
                    <input type="time" wire:model.live="horaInicio" id="horaInicio" class="campo">
                    @error('horaInicio') <p class="campo-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="horaFin" class="campo-etiqueta">Fin</label>
                    <input type="time" wire:model.live="horaFin" id="horaFin" class="campo">
                    @error('horaFin') <p class="campo-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        @if ($advertencias !== [])
            <x-aviso tono="atencion" role="status" class="mt-4">
                <p class="font-semibold">Cruces en esa franja (puedes registrarla igual):</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach ($advertencias as $advertencia)
                        <li>{{ $advertencia }}</li>
                    @endforeach
                </ul>
            </x-aviso>
        @endif

        <x-slot:pie>
            <x-boton type="button" wire:click="registrar" wire:loading.attr="disabled" wire:target="registrar">Registrar sesión</x-boton>
            <span class="min-w-0 flex-1 basis-64">Queda aprobada: la aprobó coordinación al entregar el formato. La sala se asigna al preparar.</span>
        </x-slot:pie>
    </x-tarjeta>

    <x-tarjeta titulo="Sesiones apartadas">
        <label class="mb-4 flex w-fit cursor-pointer items-center gap-2.5 text-[14px] text-gray-700">
            <input type="checkbox" wire:model.live="soloSinFormato" class="casilla">
            Solo las que no tienen formato intramural
        </label>

        @if ($sesiones->isEmpty())
            <x-mensaje-vacio titulo="No hay sesiones apartadas" descripcion="Las que registres aparecerán aquí." />
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($sesiones as $sesion)
                    <li wire:key="apartada-{{ $sesion->id }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 py-3.5">
                        <div class="min-w-0">
                            <a href="{{ route('panel.solicitudes.ver', $sesion) }}" class="text-[15px] font-semibold leading-snug text-gray-900 underline decoration-transparent underline-offset-4 hover:decoration-gray-900">{{ $sesion->casoClinico->nombre }}</a>
                            <p class="mt-0.5 text-[14px] leading-snug text-gray-500">
                                {{ $sesion->fecha->format('d/m/Y') }} {{ substr($sesion->hora_inicio, 0, 5) }}–{{ substr($sesion->hora_fin, 0, 5) }}
                                · {{ $sesion->docente->nombre }} · {{ $sesion->materia->nombre }}{{ $sesion->grupo ? ' · grupo '.$sesion->grupo : '' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($sesion->tieneFormatoIntramural())
                                <x-insignia tono="exito">Con formato intramural</x-insignia>
                            @else
                                <x-insignia tono="atencion">Sin formato intramural</x-insignia>
                            @endif
                            <x-boton variante="secundario" href="{{ route('panel.solicitudes.novedades', $sesion) }}" tamano="chico">Novedades</x-boton>
                            <x-boton variante="secundario" href="{{ route('panel.solicitudes.formato-intramural', $sesion) }}" tamano="chico">
                                {{ $sesion->tieneFormatoIntramural() ? 'Ver insumos' : 'Registrar insumos' }}
                            </x-boton>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4">{{ $sesiones->links() }}</div>
        @endif
    </x-tarjeta>
</div>
