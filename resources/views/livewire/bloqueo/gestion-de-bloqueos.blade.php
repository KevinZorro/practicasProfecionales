<div class="space-y-4">

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
            {{ $errorDeRegla }}
        </p>
    @endif

    <x-tarjeta titulo="Bloquear a un estudiante o docente">
        @if ($elegida)
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-md bg-gray-50 px-3 py-2">
                <span class="text-sm font-medium text-gray-900">{{ $elegida->nombre }} <span class="font-normal text-gray-500">· {{ $elegida->codigo_institucional ?? $elegida->email }}</span></span>
                <button type="button" wire:click="$set('personaElegida', null)" class="text-sm text-sky-800 hover:underline">Cambiar</button>
            </div>

            <label for="motivo" class="mb-1 block text-sm font-medium text-gray-700">Motivo</label>
            <textarea wire:model="motivo" id="motivo" rows="2"
                      class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600"
                      placeholder="Qué norma del reglamento incumplió, o la causal."></textarea>
            @error('motivo') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror

            <x-boton type="button" wire:click="bloquear" wire:loading.attr="disabled" class="mt-3 px-4 py-2.5">Bloquear</x-boton>
        @else
            <label for="busquedaPersona" class="mb-1 block text-sm font-medium text-gray-700">Buscar por nombre, correo o código</label>
            <input type="search" wire:model.live.debounce.400ms="busquedaPersona" id="busquedaPersona" autocomplete="off"
                   class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
            @error('personaElegida') <p class="mt-1 text-sm text-rose-700">Elige a quién bloquear.</p> @enderror

            @if ($candidatos->isNotEmpty())
                <ul class="mt-2 divide-y divide-gray-200 rounded-md border border-gray-200">
                    @foreach ($candidatos as $persona)
                        <li wire:key="candidato-{{ $persona->id }}">
                            <button type="button" wire:click="elegir({{ $persona->id }})"
                                    class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left text-sm hover:bg-sky-50">
                                <span class="min-w-0 truncate">{{ $persona->nombre }} <span class="text-gray-500">· {{ $persona->codigo_institucional ?? $persona->email }}</span></span>
                                <span class="shrink-0 font-medium text-sky-800">Elegir</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @elseif (trim($busquedaPersona) !== '')
                <p class="mt-2 text-sm text-gray-600">Ningún estudiante o docente sin bloqueo coincide con «{{ $busquedaPersona }}».</p>
            @endif
        @endif

        <x-slot:pie>
            Quien esté bloqueado no puede ingresar al laboratorio ni ser evaluado. Un docente bloqueado tampoco puede solicitar escenarios.
        </x-slot:pie>
    </x-tarjeta>

    <x-tarjeta titulo="{{ $conHistorial ? 'Todos los bloqueos' : 'Bloqueos vigentes' }}">
        <div class="mb-3 flex flex-wrap items-center gap-3">
            <input type="search" wire:model.live.debounce.400ms="busqueda" placeholder="Buscar persona" aria-label="Buscar persona"
                   class="min-w-0 flex-1 rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" wire:model.live="conHistorial" class="rounded border border-gray-300">
                Incluir los levantados
            </label>
        </div>

        @if ($bloqueos->isEmpty())
            <x-mensaje-vacio titulo="No hay bloqueos" descripcion="Nadie tiene bloqueado el acceso al laboratorio." />
        @else
            <ul class="divide-y divide-gray-200">
                @foreach ($bloqueos as $bloqueo)
                    <li wire:key="bloqueo-{{ $bloqueo->id }}" class="py-3">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900">{{ $bloqueo->persona->nombre }}</p>
                                <p class="text-sm text-gray-700">{{ $bloqueo->motivo }}</p>
                                <p class="text-xs text-gray-500">
                                    Bloqueado el {{ $bloqueo->created_at?->format('d/m/Y') }} por {{ $bloqueo->bloqueadoPor->nombre }}
                                    @if ($bloqueo->levantado_at)
                                        · levantado el {{ $bloqueo->levantado_at->format('d/m/Y') }} por {{ $bloqueo->levantadoPor?->nombre ?? 'desconocido' }}: {{ $bloqueo->motivo_levantamiento }}
                                    @endif
                                </p>
                            </div>
                            @if ($bloqueo->estaVigente())
                                <x-boton variante="secundario" type="button" wire:click="pedirMotivo({{ $bloqueo->id }})" class="px-3 py-2">Levantar</x-boton>
                            @endif
                        </div>

                        @if ($levantando === $bloqueo->id)
                            <div class="mt-2 space-y-2 rounded-md bg-gray-50 p-3">
                                <label for="levantar-{{ $bloqueo->id }}" class="block text-sm font-medium text-gray-700">Motivo para levantarlo</label>
                                <textarea wire:model="motivoLevantamiento" id="levantar-{{ $bloqueo->id }}" rows="2"
                                          class="w-full rounded-md border border-gray-300 text-sm focus:border-sky-600 focus:ring-sky-600"></textarea>
                                @error('motivoLevantamiento') <p class="text-sm text-rose-700">{{ $message }}</p> @enderror
                                <div class="flex flex-wrap gap-2">
                                    <x-boton type="button" wire:click="levantar({{ $bloqueo->id }})">Confirmar</x-boton>
                                    <x-boton variante="secundario" type="button" wire:click="$set('levantando', null)">Cancelar</x-boton>
                                </div>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-3">{{ $bloqueos->links() }}</div>
        @endif
    </x-tarjeta>
</div>
