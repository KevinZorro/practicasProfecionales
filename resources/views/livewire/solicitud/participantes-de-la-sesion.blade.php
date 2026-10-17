@php
    $noPueden = $estudiantes->filter(fn ($e) => ! $e->participacion->fueRetirado() && $impedimentos[$e->id] !== [])->count();
@endphp

<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">{{ $errorDeRegla }}</x-aviso>
    @endif

    {{-- El docente también entra a la práctica y también firma el formato. --}}
    <x-tarjeta titulo="Docente">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <x-iniciales :nombre="$docente->nombre" class="size-9 bg-gray-100 text-[13px] text-gray-700" />
                <p class="min-w-0 truncate text-[15px] font-semibold text-gray-900">{{ $docente->nombre }}</p>
            </div>
            @if ($impedimentosDelDocente === [])
                <x-insignia tono="exito">Puede ingresar</x-insignia>
            @else
                <x-insignia tono="peligro">No puede ingresar</x-insignia>
            @endif
        </div>
        @if ($impedimentosDelDocente !== [])
            <ul class="mt-3 space-y-1 text-[14px] text-rojo-800">
                @foreach ($impedimentosDelDocente as $impedimento)
                    <li>{{ $impedimento->descripcion() }}</li>
                @endforeach
            </ul>
            <p class="mt-2 text-[14px] text-gray-600">La sesión no se cancela sola: coordinación decide si se reprograma o se sustituye al docente.</p>
        @endif
        {{-- Aquí es donde se ve quién dicta; cambiarlo tiene que estar a un toque. --}}
        @can('reprogramar', $solicitud)
            <div class="mt-4 border-t border-gray-100 pt-4">
                <x-boton variante="secundario" tamano="chico" href="{{ route('panel.solicitudes.novedades', $solicitud) }}">
                    Cambiar el docente
                </x-boton>
            </div>
        @endcan
    </x-tarjeta>

    <x-tarjeta titulo="Estudiantes">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-[14px]">
                @if ($noPueden === 0)
                    <span class="text-gray-700">Todos los estudiantes de la lista pueden ingresar.</span>
                @else
                    <span class="font-semibold text-rojo-800">{{ $noPueden }} {{ $noPueden === 1 ? 'no puede' : 'no pueden' }} ingresar.</span>
                @endif
            </p>
            @if ($verificaFormatos)
                <x-boton variante="secundario" tamano="chico" href="{{ route('panel.formatos-confidencialidad.estado', ['sesion' => $solicitud->id]) }}">
                    Verificar formatos de esta sesión
                </x-boton>
            @endif
        </div>

        @if ($estudiantes->isEmpty())
            <p class="rounded-xl bg-gray-50 px-4 py-6 text-center text-[14px] text-gray-500">La sesión todavía no tiene estudiantes.</p>
        @else
            <ul class="divide-y divide-gray-100 border-y border-gray-100">
                @foreach ($estudiantes as $estudiante)
                    @php($retirado = $estudiante->participacion->fueRetirado())
                    <li wire:key="participante-{{ $estudiante->id }}" class="py-3">
                        <div class="flex flex-wrap items-start justify-between gap-x-3 gap-y-2">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <x-iniciales :nombre="$estudiante->nombre" @class(['mt-px size-8 text-[12px]', 'bg-gray-100 text-gray-700' => ! $retirado, 'bg-gray-50 text-gray-400' => $retirado]) />
                                <div class="min-w-0">
                                    <p @class(['text-[15px] font-medium leading-snug', 'text-gray-900' => ! $retirado, 'text-gray-500 line-through' => $retirado])>
                                        {{ $estudiante->nombre }}
                                    </p>
                                    @if ($retirado)
                                        <p class="mt-0.5 text-[14px] leading-snug text-gray-600">
                                            Retirado el {{ $estudiante->participacion->retirado_at->format('d/m/Y') }}
                                            por {{ $responsablesDeRetiro[$estudiante->participacion->retirado_por] ?? 'desconocido' }}: {{ $estudiante->participacion->motivo_retiro }}
                                        </p>
                                    @else
                                        @foreach ($impedimentos[$estudiante->id] as $impedimento)
                                            <p class="mt-0.5 text-[14px] leading-snug text-rojo-800">{{ $impedimento->descripcion() }}</p>
                                        @endforeach
                                    @endif
                                </div>
                            </div>

                            @if (! $retirado)
                                <div class="flex shrink-0 items-center gap-1 pl-11 sm:pl-0">
                                    @if ($impedimentos[$estudiante->id] === [])
                                        <x-insignia tono="exito">Puede ingresar</x-insignia>
                                    @else
                                        <x-insignia tono="peligro">No puede ingresar</x-insignia>
                                    @endif
                                    @if ($puedeGestionar && $retirando !== $estudiante->id)
                                        <x-boton variante="texto" tamano="chico" type="button" wire:click="pedirMotivo({{ $estudiante->id }})">Retirar</x-boton>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if ($retirando === $estudiante->id)
                            <form wire:submit="retirar({{ $estudiante->id }})" class="ml-11 mt-3 space-y-3 rounded-xl bg-gray-50 p-3.5">
                                <div>
                                    <label for="retiro-{{ $estudiante->id }}" class="campo-etiqueta">Motivo del retiro</label>
                                    <textarea wire:model="motivoRetiro" id="retiro-{{ $estudiante->id }}" rows="2" class="campo" x-init="$el.focus()"></textarea>
                                    @error('motivoRetiro') <p class="campo-error">{{ $message }}</p> @enderror
                                    <p class="campo-ayuda">Sigue en la lista, tachado, con este motivo.</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-boton variante="peligro" tamano="chico">Confirmar retiro</x-boton>
                                    <x-boton variante="secundario" tamano="chico" type="button" wire:click="$set('retirando', null)">Cancelar</x-boton>
                                </div>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($puedeGestionar)
            <div class="mt-5">
                <label for="busquedaEstudiante" class="campo-etiqueta">Agregar un estudiante</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.2-5.2M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/>
                    </svg>
                    <input type="search" wire:model.live.debounce.400ms="busquedaEstudiante" id="busquedaEstudiante" autocomplete="off"
                           placeholder="Nombre o código" class="campo pl-9">
                </div>
                @if ($resultados->isNotEmpty())
                    <ul class="mt-2 divide-y divide-gray-100 overflow-hidden rounded-xl ring-1 ring-gray-300">
                        @foreach ($resultados as $candidato)
                            <li wire:key="candidato-{{ $candidato->id }}">
                                <button type="button" wire:click="agregar({{ $candidato->id }})"
                                        class="flex min-h-11 w-full items-center justify-between gap-3 px-3.5 py-2 text-left text-[14px] transition-colors duration-150 hover:bg-gray-100 focus-visible:bg-gray-100 focus-visible:outline-none">
                                    <span class="min-w-0 truncate">{{ $candidato->nombre }} <span class="text-gray-500">· {{ $candidato->codigo_institucional ?? $candidato->email }}</span></span>
                                    <span class="shrink-0 font-semibold text-gray-900">Agregar</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @elseif (trim($busquedaEstudiante) !== '')
                    <p class="campo-ayuda">No hay estudiantes activos con ese nombre o código fuera de la lista.</p>
                @endif
            </div>
        @endif
    </x-tarjeta>
</div>
