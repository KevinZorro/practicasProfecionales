<div class="space-y-4">

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
            {{ $errorDeRegla }}
        </p>
    @endif

    <x-tarjeta>
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-base font-semibold text-gray-900">{{ $solicitud->casoClinico->nombre }}</p>
                <p class="text-sm text-gray-600">
                    {{ $solicitud->materia->nombre }}{{ $solicitud->grupo ? ' · grupo '.$solicitud->grupo : '' }}
                </p>
            </div>
            <x-etiqueta-estado :estado="$solicitud->estado" />
        </div>

        <dl class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-dato etiqueta="Fecha">{{ $solicitud->fecha->format('d/m/Y') }}</x-dato>
            <x-dato etiqueta="Hora">{{ substr($solicitud->hora_inicio, 0, 5) }}–{{ substr($solicitud->hora_fin, 0, 5) }}</x-dato>
            <x-dato etiqueta="Estudiantes">{{ $solicitud->cantidad_estudiantes }}</x-dato>
            <x-dato etiqueta="Sala">{{ $solicitud->preparacion?->sala?->nombreCompleto() ?? 'Se asigna al preparar' }}</x-dato>
        </dl>
    </x-tarjeta>

    {{-- El docente también entra a la práctica y también firma el formato. --}}
    <x-tarjeta titulo="Docente">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <p class="text-sm font-medium text-gray-900">{{ $docente->nombre }}</p>
            @if ($impedimentosDelDocente === [])
                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-900 ring-1 ring-inset ring-emerald-600/20">Puede ingresar</span>
            @else
                <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-900 ring-1 ring-inset ring-rose-600/20">No puede ingresar</span>
            @endif
        </div>
        @foreach ($impedimentosDelDocente as $impedimento)
            <p class="mt-1 text-sm text-rose-800">{{ $impedimento->descripcion() }}</p>
        @endforeach
        @if ($impedimentosDelDocente !== [])
            <p class="mt-2 text-sm text-gray-600">La sesión no se cancela sola: coordinación decide si se reprograma o se sustituye al docente.</p>
        @endif
    </x-tarjeta>

    @php
        $noPueden = $estudiantes->filter(fn ($e) => ! $e->participacion->fueRetirado() && $impedimentos[$e->id] !== [])->count();
    @endphp

    <x-tarjeta titulo="Estudiantes">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-700">
                @if ($noPueden === 0)
                    Todos los estudiantes de la lista pueden ingresar.
                @else
                    <span class="font-medium text-rose-800">{{ $noPueden }} {{ $noPueden === 1 ? 'no puede' : 'no pueden' }} ingresar.</span>
                @endif
            </p>
            @if ($verificaFormatos)
                <x-boton variante="secundario" href="{{ route('panel.formatos-confidencialidad.estado', ['sesion' => $solicitud->id]) }}" class="px-3 py-2">
                    Verificar formatos de esta sesión
                </x-boton>
            @endif
        </div>

        @if ($estudiantes->isEmpty())
            <x-mensaje-vacio titulo="La sesión todavía no tiene estudiantes" descripcion="Agrégalos aquí abajo." />
        @else
            <ul class="divide-y divide-gray-200">
                @foreach ($estudiantes as $estudiante)
                    @php($retirado = $estudiante->participacion->fueRetirado())
                    <li wire:key="participante-{{ $estudiante->id }}" class="py-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p @class(['text-sm font-medium', 'text-gray-900' => ! $retirado, 'text-gray-500 line-through' => $retirado])>
                                    {{ $estudiante->nombre }}
                                </p>
                                @if ($retirado)
                                    <p class="text-sm text-gray-600">
                                        Retirado el {{ $estudiante->participacion->retirado_at->format('d/m/Y') }}
                                        por {{ $responsablesDeRetiro[$estudiante->participacion->retirado_por] ?? 'desconocido' }}: {{ $estudiante->participacion->motivo_retiro }}
                                    </p>
                                @else
                                    @foreach ($impedimentos[$estudiante->id] as $impedimento)
                                        <p class="text-sm text-rose-800">{{ $impedimento->descripcion() }}</p>
                                    @endforeach
                                @endif
                            </div>

                            @if (! $retirado)
                                <div class="flex shrink-0 items-center gap-2">
                                    @if ($impedimentos[$estudiante->id] === [])
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-900 ring-1 ring-inset ring-emerald-600/20">Puede ingresar</span>
                                    @else
                                        <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-medium text-rose-900 ring-1 ring-inset ring-rose-600/20">No puede ingresar</span>
                                    @endif
                                    @if ($puedeGestionar)
                                        <button type="button" wire:click="pedirMotivo({{ $estudiante->id }})"
                                                class="rounded-md px-2 py-1 text-sm text-rose-700 hover:bg-rose-50">Retirar</button>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if ($retirando === $estudiante->id)
                            <div class="mt-2 space-y-2 rounded-md bg-gray-50 p-3">
                                <label for="retiro-{{ $estudiante->id }}" class="block text-sm font-medium text-gray-700">Motivo del retiro</label>
                                <textarea wire:model="motivoRetiro" id="retiro-{{ $estudiante->id }}" rows="2"
                                          class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600"></textarea>
                                @error('motivoRetiro') <p class="text-sm text-rose-700">{{ $message }}</p> @enderror
                                <div class="flex flex-wrap gap-2">
                                    <x-boton type="button" wire:click="retirar({{ $estudiante->id }})">Confirmar retiro</x-boton>
                                    <x-boton variante="secundario" type="button" wire:click="$set('retirando', null)">Cancelar</x-boton>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($puedeGestionar)
            <div class="mt-4 border-t border-gray-200 pt-4">
                <label for="busquedaEstudiante" class="mb-1 block text-sm font-medium text-gray-700">Agregar un estudiante</label>
                <input type="search" wire:model.live.debounce.400ms="busquedaEstudiante" id="busquedaEstudiante" autocomplete="off"
                       placeholder="Nombre o código"
                       class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
                @if ($resultados->isNotEmpty())
                    <ul class="mt-2 divide-y divide-gray-200 rounded-md border border-gray-200">
                        @foreach ($resultados as $candidato)
                            <li wire:key="candidato-{{ $candidato->id }}">
                                <button type="button" wire:click="agregar({{ $candidato->id }})"
                                        class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-sm hover:bg-sky-50">
                                    <span class="min-w-0 truncate">{{ $candidato->nombre }} <span class="text-gray-500">· {{ $candidato->codigo_institucional ?? $candidato->email }}</span></span>
                                    <span class="shrink-0 font-medium text-sky-800">Agregar</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </x-tarjeta>
</div>
