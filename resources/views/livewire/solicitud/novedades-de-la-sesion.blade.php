<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">{{ $errorDeRegla }}</x-aviso>
    @endif

    <div class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_19rem]">
        <div class="min-w-0 space-y-5">
            <x-tarjeta titulo="Reprogramar" descripcion="Sigue aprobada. Si cambia la fecha o la hora, la sala se vuelve a asignar al preparar.">
                <form wire:submit="reprogramar" id="form-reprogramar" class="grid gap-4 sm:grid-cols-2">
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
                    <div class="sm:col-span-2">
                        <label for="casoClinicoId" class="campo-etiqueta">Escenario clínico</label>
                        <select wire:model="casoClinicoId" id="casoClinicoId" class="campo">
                            @foreach ($casosClinicos as $caso)
                                <option value="{{ $caso->id }}">{{ $caso->nombre }}</option>
                            @endforeach
                        </select>
                        <p class="campo-ayuda">Si cambia el escenario, hay que volver a registrar el formato intramural.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="motivo" class="campo-etiqueta">Motivo</label>
                        <textarea wire:model="motivo" id="motivo" rows="2" class="campo"></textarea>
                        @error('motivo') <p class="campo-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="constancia" class="campo-etiqueta">Constancia de comunicación con el docente</label>
                        <textarea wire:model="constancia" id="constancia" rows="2" class="campo"
                                  placeholder="Cómo, cuándo y con quién se habló. Ej.: llamada del 9/10 a las 3 p. m., aceptó el cambio."></textarea>
                        @error('constancia') <p class="campo-error">{{ $message }}</p> @enderror
                    </div>
                </form>

                @if ($advertencias !== [])
                    <x-aviso tono="atencion" role="status" class="mt-4">
                        <p class="font-semibold">Cruces en la franja nueva (puedes reprogramar igual):</p>
                        <ul class="mt-1 list-disc space-y-0.5 pl-5">
                            @foreach ($advertencias as $advertencia)
                                <li>{{ $advertencia }}</li>
                            @endforeach
                        </ul>
                    </x-aviso>
                @endif

                <x-slot:pie>
                    <x-boton form="form-reprogramar" wire:loading.attr="disabled" wire:target="reprogramar">Reprogramar</x-boton>
                </x-slot:pie>
            </x-tarjeta>

            <x-tarjeta titulo="Sustituir al docente" descripcion="Las horas de la sesión se atribuyen a quien la dicte. Para devolverla al titular, elígelo a él.">
                <p class="mb-4 text-[14px] text-gray-700">
                    @if ($solicitud->docenteQueDicta)
                        Ahora la dicta <strong class="font-semibold text-gray-900">{{ $solicitud->docenteQueDicta->nombre }}</strong>, en reemplazo de {{ $solicitud->docente->nombre }}.
                    @else
                        Ahora la dicta su titular, <strong class="font-semibold text-gray-900">{{ $solicitud->docente->nombre }}</strong>.
                    @endif
                </p>
                <form wire:submit="sustituir" id="form-sustituir" class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="reemplazoId" class="campo-etiqueta">Quién dictará la sesión</label>
                        <select wire:model="reemplazoId" id="reemplazoId" class="campo">
                            <option value="">Elige un docente</option>
                            @foreach ($docentes as $docente)
                                <option value="{{ $docente->id }}">{{ $docente->nombre }}{{ $docente->id === $solicitud->docente_id ? ' (titular)' : '' }}</option>
                            @endforeach
                        </select>
                        @error('reemplazoId') <p class="campo-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="motivoSustitucion" class="campo-etiqueta">Novedad</label>
                        <textarea wire:model="motivoSustitucion" id="motivoSustitucion" rows="2" class="campo"></textarea>
                        @error('motivoSustitucion') <p class="campo-error">{{ $message }}</p> @enderror
                    </div>
                </form>
                <x-slot:pie>
                    <x-boton form="form-sustituir" wire:loading.attr="disabled" wire:target="sustituir">Registrar sustitución</x-boton>
                </x-slot:pie>
            </x-tarjeta>
        </div>

        <x-tarjeta titulo="Historial de novedades">
            @if ($solicitud->reprogramaciones->isEmpty() && $solicitud->sustituciones->isEmpty())
                <p class="text-[14px] text-gray-500">Sin novedades: se dicta como se aprobó.</p>
            @else
                <ol class="space-y-4 text-[14px] leading-snug text-gray-700">
                    @foreach ($solicitud->reprogramaciones as $cambio)
                        <li wire:key="reprogramacion-{{ $cambio->id }}" class="space-y-1">
                            <p class="font-semibold text-gray-900">
                                Reprogramada el {{ $cambio->created_at?->format('d/m/Y') }} por {{ $cambio->reprogramadaPor->nombre }}
                            </p>
                            <p>
                                De {{ $cambio->fecha_anterior->format('d/m/Y') }} {{ substr($cambio->hora_inicio_anterior, 0, 5) }} ({{ $cambio->casoClinicoAnterior->nombre }})
                                a {{ $cambio->fecha_nueva->format('d/m/Y') }} {{ substr($cambio->hora_inicio_nueva, 0, 5) }} ({{ $cambio->casoClinicoNuevo->nombre }}).
                            </p>
                            <p><span class="text-gray-500">Motivo:</span> {{ $cambio->motivo }}</p>
                            <p><span class="text-gray-500">Comunicación:</span> {{ $cambio->constancia_comunicacion }}</p>
                        </li>
                    @endforeach
                    @foreach ($solicitud->sustituciones as $sustitucion)
                        <li wire:key="sustitucion-{{ $sustitucion->id }}" class="space-y-1">
                            <p class="font-semibold text-gray-900">
                                Sustitución del {{ $sustitucion->created_at?->format('d/m/Y') }}, registrada por {{ $sustitucion->registradaPor->nombre }}
                            </p>
                            <p>{{ $sustitucion->docenteAnterior->nombre }} → {{ $sustitucion->docenteNuevo->nombre }}. {{ $sustitucion->motivo }}</p>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-tarjeta>
    </div>
</div>
