<div class="space-y-4">

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">{{ $errorDeRegla }}</p>
    @endif

    @if ($registra)
        <x-tarjeta titulo="Sesiones de evaluación por registrar">
            @if ($porEvaluar->isEmpty())
                <x-mensaje-vacio
                    titulo="No tienes sesiones de evaluación pendientes"
                    descripcion="Aquí aparecen tus sesiones de tipo evaluación ya aprobadas que aún no tienen evaluación registrada."
                />
            @else
                <ul class="divide-y divide-gray-200">
                    @foreach ($porEvaluar as $sesion)
                        <li wire:key="por-evaluar-{{ $sesion->id }}" class="py-3">
                            <p class="text-sm font-medium text-gray-900">{{ $sesion->casoClinico->nombre }}</p>
                            <p class="text-sm text-gray-600">
                                {{ $sesion->materia->nombre }}{{ $sesion->grupo ? ' · grupo '.$sesion->grupo : '' }}
                                · {{ $sesion->fecha->format('d/m/Y') }} {{ substr($sesion->hora_inicio, 0, 5) }}
                            </p>

                            @if ($tiposPorSesion[$sesion->id]->isEmpty())
                                <p class="mt-2 text-sm text-amber-800">
                                    La materia no tiene tipos de evaluación activos. El ADMIN los define en Administración.
                                </p>
                            @else
                                <div class="mt-2 flex flex-wrap items-end gap-2">
                                    <div class="min-w-0 flex-1">
                                        <label for="tipo-{{ $sesion->id }}" class="sr-only">Tipo de evaluación</label>
                                        <select wire:model="tipoElegido.{{ $sesion->id }}" id="tipo-{{ $sesion->id }}"
                                                class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                                            <option value="">Elige el tipo de evaluación</option>
                                            @foreach ($tiposPorSesion[$sesion->id] as $tipo)
                                                <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <x-boton type="button" wire:click="registrar({{ $sesion->id }})" class="px-4 py-2.5">Registrar evaluación</x-boton>
                                </div>
                                @error('tipoElegido.'.$sesion->id) <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
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
                <ul class="divide-y divide-gray-200">
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
                <ul class="divide-y divide-gray-200">
                    @foreach ($todas as $evaluacion)
                        @include('livewire.evaluacion.partes.fila', ['evaluacion' => $evaluacion, 'cuantos' => $evaluacion->estudiantes_count, 'conDocente' => true])
                    @endforeach
                </ul>
                <div class="mt-3">{{ $todas->links() }}</div>
            @endif
        </x-tarjeta>
    @endif
</div>
