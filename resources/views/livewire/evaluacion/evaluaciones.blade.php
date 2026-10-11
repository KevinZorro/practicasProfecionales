<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">{{ $errorDeRegla }}</x-aviso>
    @endif

    @if ($registra)
        <x-tarjeta titulo="Sesiones de evaluación por registrar">
            @if ($porEvaluar->isEmpty())
                <x-mensaje-vacio
                    titulo="No tienes sesiones de evaluación pendientes"
                    descripcion="Aquí aparecen tus sesiones de tipo evaluación ya aprobadas que aún no tienen evaluación registrada."
                />
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($porEvaluar as $sesion)
                        <li wire:key="por-evaluar-{{ $sesion->id }}" class="py-3">
                            <p class="text-[15px] font-semibold text-gray-900">{{ $sesion->casoClinico->nombre }}</p>
                            <p class="text-[14px] text-gray-500">
                                {{ $sesion->materia->nombre }}{{ $sesion->grupo ? ' · grupo '.$sesion->grupo : '' }}
                                · {{ $sesion->fecha->format('d/m/Y') }} {{ substr($sesion->hora_inicio, 0, 5) }}
                            </p>

                            @if ($tiposPorSesion[$sesion->id]->isEmpty())
                                <p class="mt-2 text-[14px] font-medium text-amber-800">
                                    La materia no tiene tipos de evaluación activos. El ADMIN los define en Administración.
                                </p>
                            @else
                                <div class="mt-3 flex flex-wrap items-end gap-2">
                                    <div class="min-w-0 flex-1 basis-56">
                                        <label for="tipo-{{ $sesion->id }}" class="sr-only">Tipo de evaluación</label>
                                        <select wire:model="tipoElegido.{{ $sesion->id }}" id="tipo-{{ $sesion->id }}"
                                                class="campo">
                                            <option value="">Elige el tipo de evaluación</option>
                                            @foreach ($tiposPorSesion[$sesion->id] as $tipo)
                                                <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <x-boton type="button" wire:click="registrar({{ $sesion->id }})">Registrar evaluación</x-boton>
                                </div>
                                @error('tipoElegido.'.$sesion->id) <p class="campo-error">{{ $message }}</p> @enderror
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-tarjeta>

        <x-tarjeta titulo="Mis evaluaciones">
            @if ($mias->isEmpty())
                <x-mensaje-vacio titulo="Todavía no has registrado evaluaciones" descripcion="Las que registres aparecerán aquí." />
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($mias as $evaluacion)
                        @include('livewire.evaluacion.partes.fila', ['evaluacion' => $evaluacion, 'cuantos' => $evaluacion->estudiantes->count()])
                    @endforeach
                </ul>
            @endif
        </x-tarjeta>
    @endif

    @if ($todas !== null)
        <x-tarjeta titulo="Todas las evaluaciones">
            @if ($todas->isEmpty())
                <x-mensaje-vacio titulo="Todavía no hay evaluaciones registradas" descripcion="Las registran los docentes sobre sus sesiones de evaluación aprobadas." />
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($todas as $evaluacion)
                        @include('livewire.evaluacion.partes.fila', ['evaluacion' => $evaluacion, 'cuantos' => $evaluacion->estudiantes_count, 'conDocente' => true])
                    @endforeach
                </ul>
                <div class="mt-4">{{ $todas->links() }}</div>
            @endif
        </x-tarjeta>
    @endif
</div>
