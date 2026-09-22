<div class="space-y-4">

    @if (session('estado'))
        <p class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ring-1 ring-inset ring-emerald-600/20" role="status">
            {{ session('estado') }}
        </p>
    @endif

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
            {{ $errorDeRegla }}
        </p>
    @endif

    {{-- Próximos a vencer: el aviso que evita que el pasante se quede fuera
         sin que nadie lo hubiera previsto (RF63, RF64). --}}
    @if ($proximosAVencer->isNotEmpty())
        <x-tarjeta titulo="Vencen en los próximos {{ $diasDeAviso }} días">
            <ul class="space-y-2">
                @foreach ($proximosAVencer as $porVencer)
                    <li class="flex flex-col gap-0.5" wire:key="vence-{{ $porVencer->id }}">
                        <span class="text-sm font-medium text-gray-900">{{ $porVencer->nombre }}</span>
                        @foreach ($porVencer->roles as $role)
                            <span class="text-sm text-amber-800">
                                {{ \App\Enums\Rol::from($role->name)->etiqueta() }}
                                · hasta el {{ $role->pivot->hasta->format('d/m/Y') }}
                            </span>
                        @endforeach
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif

    <x-tarjeta>
        <label for="buscar" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Buscar</label>
        <input type="search" wire:model.live.debounce.400ms="busqueda" id="buscar" placeholder="Nombre, correo o código"
               class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
        <p class="mt-2 text-sm text-gray-600">
            {{ trans_choice(':count persona|:count personas', $usuarios->total(), ['count' => $usuarios->total()]) }}
        </p>
    </x-tarjeta>

    @if ($usuarios->isEmpty())
        <x-mensaje-vacio titulo="No hay nadie que coincida" descripcion="Prueba con otro nombre o código." />
    @else
        <ul class="space-y-2">
            @foreach ($usuarios as $usuario)
                <li wire:key="usuario-{{ $usuario->id }}">
                    <x-tarjeta>
                        {{-- Columna y no fila: a 390 px el nombre y las etiquetas
                             no caben juntos y el nombre se corta. --}}
                        <div class="flex flex-col gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $usuario->nombre }}</p>
                                <p class="truncate text-sm text-gray-600">{{ $usuario->email }}</p>
                            </div>

                            @if ($usuario->roles->isEmpty())
                                <p class="text-sm text-gray-500">Sin ningún rol asignado.</p>
                            @else
                                <ul class="space-y-1.5">
                                    @foreach ($usuario->roles as $role)
                                        @php($rol = \App\Enums\Rol::from($role->name))
                                        <li class="flex flex-wrap items-center gap-2" wire:key="rol-{{ $usuario->id }}-{{ $role->id }}">
                                            @if ($role->pivot->hasta)
                                                <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-900 ring-1 ring-inset ring-amber-600/20">
                                                    {{ $rol->etiqueta() }} · temporal hasta {{ $role->pivot->hasta->format('d/m/Y') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/20">
                                                    {{ $rol->etiqueta() }}
                                                </span>
                                            @endif

                                            @can('revocar', \App\Models\AsignacionDeRol::class)
                                                <button type="button" wire:click="revocar({{ $usuario->id }}, '{{ $rol->value }}')"
                                                        wire:loading.attr="disabled"
                                                        class="text-xs font-medium text-rose-700 underline underline-offset-2 hover:text-rose-900">
                                                    Revocar
                                                </button>
                                            @endcan
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        @can('asignar', \App\Models\AsignacionDeRol::class)
                            <x-slot:pie>
                                @if ($asignandoA === $usuario->id)
                                    <div class="w-full space-y-3">
                                        <div>
                                            <label for="rol-{{ $usuario->id }}" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Rol</label>
                                            <select wire:model.live="rolElegido" id="rol-{{ $usuario->id }}"
                                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                                                <option value="">Elige un rol</option>
                                                @foreach ($rolesAsignables as $asignable)
                                                    <option value="{{ $asignable->value }}">{{ $asignable->etiqueta() }}</option>
                                                @endforeach
                                            </select>
                                            @error('rolElegido') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                                        </div>

                                        <div>
                                            <label for="hasta-{{ $usuario->id }}" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">
                                                Hasta (opcional)
                                            </label>
                                            <input type="date" wire:model="hasta" id="hasta-{{ $usuario->id }}"
                                                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                                            <p class="mt-1 text-sm text-gray-600">
                                                Vale todo ese día y deja de tener efecto al día siguiente. Déjalo vacío para un rol permanente.
                                            </p>
                                            @error('hasta') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                                        </div>

                                        {{-- RF63: delegar la facultad de aprobar exige justificación. --}}
                                        @if ($rolElegido === \App\Enums\Rol::Coordinador->value)
                                            <div>
                                                <label for="motivo-{{ $usuario->id }}" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">
                                                    Motivo de la delegación
                                                </label>
                                                <textarea wire:model="motivo" id="motivo-{{ $usuario->id }}" rows="2"
                                                          class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600"></textarea>
                                                <p class="mt-1 text-sm text-gray-600">Obligatorio al elevar a coordinador.</p>
                                            </div>
                                        @endif

                                        <div class="flex flex-wrap gap-2">
                                            <x-boton wire:click="asignar" wire:loading.attr="disabled" class="px-4 py-2.5">Asignar</x-boton>
                                            <x-boton variante="secundario" type="button" wire:click="cerrar" class="px-4 py-2.5">Cancelar</x-boton>
                                        </div>
                                    </div>
                                @else
                                    <x-boton variante="secundario" type="button" wire:click="abrir({{ $usuario->id }})">
                                        Asignar un rol
                                    </x-boton>
                                @endif
                            </x-slot:pie>
                        @endcan
                    </x-tarjeta>
                </li>
            @endforeach
        </ul>

        <div>{{ $usuarios->links() }}</div>
    @endif
</div>
