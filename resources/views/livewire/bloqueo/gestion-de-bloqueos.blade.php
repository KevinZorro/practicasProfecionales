<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">
            {{ $errorDeRegla }}
        </x-aviso>
    @endif

    <x-tarjeta titulo="Bloquear a un estudiante o docente">
        @if ($elegida)
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-gray-50 py-2 pl-3.5 pr-1.5">
                <span class="flex min-w-0 items-center gap-2.5 text-[15px] font-semibold text-gray-900">
                    <x-iniciales :nombre="$elegida->nombre" class="size-8 bg-white text-[12px] text-gray-700" />
                    <span class="min-w-0">{{ $elegida->nombre }} <span class="font-normal text-gray-500">· {{ $elegida->codigo_institucional ?? $elegida->email }}</span></span>
                </span>
                <x-boton variante="texto" tamano="chico" type="button" wire:click="$set('personaElegida', null)">Cambiar</x-boton>
            </div>

            <label for="motivo" class="campo-etiqueta">Motivo</label>
            <textarea wire:model="motivo" id="motivo" rows="2"
                      class="campo"
                      placeholder="Qué norma del reglamento incumplió, o la causal."></textarea>
            @error('motivo') <p class="campo-error">{{ $message }}</p> @enderror

            <x-boton variante="peligro" type="button" wire:click="bloquear" wire:loading.attr="disabled" wire:target="bloquear" class="mt-4">Bloquear</x-boton>
        @else
            <label for="busquedaPersona" class="campo-etiqueta">Buscar por nombre, correo o código</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.2-5.2M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/>
                </svg>
                <input type="search" wire:model.live.debounce.400ms="busquedaPersona" id="busquedaPersona" autocomplete="off" class="campo pl-9">
            </div>
            @error('personaElegida') <p class="campo-error">Elige a quién bloquear.</p> @enderror

            @if ($candidatos->isNotEmpty())
                <ul class="mt-2 divide-y divide-gray-100 overflow-hidden rounded-xl ring-1 ring-gray-300">
                    @foreach ($candidatos as $persona)
                        <li wire:key="candidato-{{ $persona->id }}">
                            <button type="button" wire:click="elegir({{ $persona->id }})"
                                    class="flex min-h-11 w-full items-center justify-between gap-3 px-3.5 py-2 text-left text-[14px] transition-colors duration-150 hover:bg-gray-50 focus-visible:bg-gray-50 focus-visible:outline-none">
                                <span class="min-w-0 truncate">{{ $persona->nombre }} <span class="text-gray-500">· {{ $persona->codigo_institucional ?? $persona->email }}</span></span>
                                <span class="shrink-0 font-semibold text-gray-900">Elegir</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @elseif (trim($busquedaPersona) !== '')
                <p class="campo-ayuda">Ningún estudiante o docente sin bloqueo coincide con «{{ $busquedaPersona }}».</p>
            @endif
        @endif

        <x-slot:pie>
            Quien esté bloqueado no puede ingresar al laboratorio ni ser evaluado. Un docente bloqueado tampoco puede solicitar escenarios.
        </x-slot:pie>
    </x-tarjeta>

    <x-tarjeta titulo="{{ $conHistorial ? 'Todos los bloqueos' : 'Bloqueos vigentes' }}">
        <div class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-3">
            <input type="search" wire:model.live.debounce.400ms="busqueda" placeholder="Buscar persona" aria-label="Buscar persona"
                   class="campo min-w-0 flex-1 basis-56">
            <label class="flex cursor-pointer items-center gap-2.5 text-[14px] text-gray-700">
                <input type="checkbox" wire:model.live="conHistorial" class="casilla">
                Incluir los levantados
            </label>
        </div>

        @if ($bloqueos->isEmpty())
            <x-mensaje-vacio titulo="No hay bloqueos" descripcion="Nadie tiene bloqueado el acceso al laboratorio." />
        @else
            <ul class="divide-y divide-gray-100">
                @foreach ($bloqueos as $bloqueo)
                    <li wire:key="bloqueo-{{ $bloqueo->id }}" class="py-3.5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="flex flex-wrap items-center gap-2 text-[15px] font-semibold text-gray-900">
                                    {{ $bloqueo->persona->nombre }}
                                    @if ($bloqueo->estaVigente())
                                        <x-insignia tono="peligro">Vigente</x-insignia>
                                    @else
                                        <x-insignia>Levantado</x-insignia>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-[14px] text-gray-700">{{ $bloqueo->motivo }}</p>
                                <p class="mt-0.5 text-[13px] text-gray-500">
                                    Bloqueado el {{ $bloqueo->created_at?->format('d/m/Y') }} por {{ $bloqueo->bloqueadoPor->nombre }}
                                    @if ($bloqueo->levantado_at)
                                        · levantado el {{ $bloqueo->levantado_at->format('d/m/Y') }} por {{ $bloqueo->levantadoPor?->nombre ?? 'desconocido' }}: {{ $bloqueo->motivo_levantamiento }}
                                    @endif
                                </p>
                            </div>
                            @if ($bloqueo->estaVigente())
                                <x-boton variante="secundario" tamano="chico" type="button" wire:click="pedirMotivo({{ $bloqueo->id }})">Levantar</x-boton>
                            @endif
                        </div>

                        @if ($levantando === $bloqueo->id)
                            <div class="mt-3 space-y-3 rounded-xl bg-gray-50 p-3.5">
                                <div>
                                    <label for="levantar-{{ $bloqueo->id }}" class="campo-etiqueta">Motivo para levantarlo</label>
                                    <textarea wire:model="motivoLevantamiento" id="levantar-{{ $bloqueo->id }}" rows="2" class="campo" x-init="$el.focus()"></textarea>
                                    @error('motivoLevantamiento') <p class="campo-error">{{ $message }}</p> @enderror
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <x-boton tamano="chico" type="button" wire:click="levantar({{ $bloqueo->id }})">Levantar el bloqueo</x-boton>
                                    <x-boton variante="secundario" tamano="chico" type="button" wire:click="$set('levantando', null)">Cancelar</x-boton>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $bloqueos->links() }}</div>
        @endif
    </x-tarjeta>
</div>
