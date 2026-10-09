<div class="space-y-4">

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">{{ $errorDeRegla }}</p>
    @endif

    <x-tarjeta>
        <p class="text-base font-semibold text-gray-900">{{ $solicitud->casoClinico->nombre }}</p>
        <p class="text-sm text-gray-600">
            {{ $solicitud->fecha->format('d/m/Y') }} {{ substr($solicitud->hora_inicio, 0, 5) }}–{{ substr($solicitud->hora_fin, 0, 5) }}
            · {{ $solicitud->materia->nombre }}
        </p>
        <p class="mt-1 text-sm text-gray-700">
            Dicta: <span class="font-medium">{{ ($solicitud->docenteQueDicta ?? $solicitud->docente)->nombre }}</span>
            @if ($solicitud->docenteQueDicta)
                · en reemplazo de {{ $solicitud->docente->nombre }}
            @endif
        </p>
    </x-tarjeta>

    <x-tarjeta titulo="Reprogramar">
        <div class="grid gap-4 sm:grid-cols-2">
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
            <div class="sm:col-span-2">
                <label for="casoClinicoId" class="mb-1 block text-sm font-medium text-gray-700">Escenario clínico</label>
                <select wire:model="casoClinicoId" id="casoClinicoId" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    @foreach ($casosClinicos as $caso)
                        <option value="{{ $caso->id }}">{{ $caso->nombre }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">Si cambia el escenario, hay que volver a registrar el formato intramural.</p>
            </div>
            <div class="sm:col-span-2">
                <label for="motivo" class="mb-1 block text-sm font-medium text-gray-700">Motivo</label>
                <textarea wire:model="motivo" id="motivo" rows="2" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600"></textarea>
                @error('motivo') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label for="constancia" class="mb-1 block text-sm font-medium text-gray-700">Constancia de comunicación con el docente</label>
                <textarea wire:model="constancia" id="constancia" rows="2" placeholder="Cómo, cuándo y con quién se habló. Ej.: llamada del 9/10 a las 3 p. m., aceptó el cambio."
                          class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600"></textarea>
                @error('constancia') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
        </div>

        @if ($advertencias !== [])
            <div class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20" role="status">
                <p class="font-medium">Cruces en la franja nueva (puedes reprogramar igual):</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($advertencias as $advertencia)
                        <li>{{ $advertencia }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-slot:pie>
            <div class="flex flex-wrap items-center gap-3">
                <x-boton type="button" wire:click="reprogramar" wire:loading.attr="disabled" class="px-4 py-2.5">Reprogramar</x-boton>
                <span>Sigue aprobada. Si cambia la fecha o la hora, la sala se vuelve a asignar al preparar.</span>
            </div>
        </x-slot:pie>
    </x-tarjeta>

    <x-tarjeta titulo="Sustituir al docente">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="reemplazoId" class="mb-1 block text-sm font-medium text-gray-700">Quién dictará la sesión</label>
                <select wire:model="reemplazoId" id="reemplazoId" class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Elige un docente</option>
                    @foreach ($docentes as $docente)
                        <option value="{{ $docente->id }}">{{ $docente->nombre }}{{ $docente->id === $solicitud->docente_id ? ' (titular)' : '' }}</option>
                    @endforeach
                </select>
                @error('reemplazoId') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="motivoSustitucion" class="mb-1 block text-sm font-medium text-gray-700">Novedad</label>
                <textarea wire:model="motivoSustitucion" id="motivoSustitucion" rows="2"
                          class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600"></textarea>
                @error('motivoSustitucion') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
        </div>
        <x-slot:pie>
            <div class="flex flex-wrap items-center gap-3">
                <x-boton type="button" wire:click="sustituir" class="px-4 py-2.5">Registrar sustitución</x-boton>
                <span>Las horas de la sesión se atribuyen a quien la dicte. Para devolverla al titular, elígelo a él.</span>
            </div>
        </x-slot:pie>
    </x-tarjeta>

    @if ($solicitud->reprogramaciones->isNotEmpty() || $solicitud->sustituciones->isNotEmpty())
        <x-tarjeta titulo="Historial de novedades">
            <ul class="space-y-3 text-sm text-gray-700">
                @foreach ($solicitud->reprogramaciones as $cambio)
                    <li wire:key="reprogramacion-{{ $cambio->id }}">
                        <p class="font-medium text-gray-900">
                            Reprogramada el {{ $cambio->created_at?->format('d/m/Y') }} por {{ $cambio->reprogramadaPor->nombre }}
                        </p>
                        <p>
                            De {{ $cambio->fecha_anterior->format('d/m/Y') }} {{ substr($cambio->hora_inicio_anterior, 0, 5) }} ({{ $cambio->casoClinicoAnterior->nombre }})
                            a {{ $cambio->fecha_nueva->format('d/m/Y') }} {{ substr($cambio->hora_inicio_nueva, 0, 5) }} ({{ $cambio->casoClinicoNuevo->nombre }}).
                        </p>
                        <p>Motivo: {{ $cambio->motivo }}</p>
                        <p>Comunicación: {{ $cambio->constancia_comunicacion }}</p>
                    </li>
                @endforeach
                @foreach ($solicitud->sustituciones as $sustitucion)
                    <li wire:key="sustitucion-{{ $sustitucion->id }}">
                        <p class="font-medium text-gray-900">
                            Sustitución del {{ $sustitucion->created_at?->format('d/m/Y') }}, registrada por {{ $sustitucion->registradaPor->nombre }}
                        </p>
                        <p>{{ $sustitucion->docenteAnterior->nombre }} → {{ $sustitucion->docenteNuevo->nombre }}. {{ $sustitucion->motivo }}</p>
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif
</div>
