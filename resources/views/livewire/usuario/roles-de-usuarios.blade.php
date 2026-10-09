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
        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600">
                {{ trans_choice(':count persona|:count personas', $usuarios->total(), ['count' => $usuarios->total()]) }}
            </p>
            {{-- RF22: la mayoría llegan de la sincronización; aquí, quien no figura en ella. --}}
            @can('create', \App\Models\User::class)
                <x-boton href="{{ route('panel.usuarios.nueva') }}">Nueva cuenta</x-boton>
            @endcan
        </div>
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
                                <p class="text-xs text-gray-600">
                                    {{ collect([$usuario->origen->etiqueta(), $usuario->programa])->filter()->implode(' · ') }}
                                </p>
                            </div>

                            {{-- Dos motivos distintos para no entrar: la vigencia institucional
                                 (la escribe la sincronización) y la marca del ADMIN (RF22). --}}
                            @if ($usuario->estaDeshabilitado())
                                <p class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20">
                                    Deshabilitada el {{ $usuario->deshabilitado_at->format('d/m/Y') }}: {{ $usuario->motivo_deshabilitacion }}
                                </p>
                            @elseif ($usuario->estado === \App\Enums\EstadoUsuario::Inactivo)
                                <p class="rounded-md bg-gray-100 px-3 py-2 text-sm text-gray-700 ring-1 ring-inset ring-gray-500/20">
                                    Sin vigencia institucional: no puede entrar.
                                </p>
                            @endif

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

                            <div class="flex flex-wrap gap-x-4 gap-y-1">
                                @can('update', $usuario)
                                    <a href="{{ route('panel.usuarios.editar', $usuario) }}" wire:navigate
                                       class="text-sm font-medium text-sky-700 underline underline-offset-2 hover:text-sky-900">Editar datos</a>
                                @endcan
                                @if ($usuario->estaDeshabilitado())
                                    @can('habilitar', $usuario)
                                        <button type="button" wire:click="abrirAcceso({{ $usuario->id }})"
                                                class="text-sm font-medium text-emerald-700 underline underline-offset-2 hover:text-emerald-900">Volver a habilitar</button>
                                    @endcan
                                @else
                                    @can('deshabilitar', $usuario)
                                        <button type="button" wire:click="abrirAcceso({{ $usuario->id }})"
                                                class="text-sm font-medium text-rose-700 underline underline-offset-2 hover:text-rose-900">Deshabilitar</button>
                                    @endcan
                                @endif
                            </div>

                            @if ($cambiandoAcceso === $usuario->id)
                                <div class="space-y-2">
                                    <label for="motivo-acceso-{{ $usuario->id }}" class="block text-xs font-medium uppercase tracking-wide text-gray-500">
                                        {{ $usuario->estaDeshabilitado() ? 'Por qué se vuelve a habilitar' : 'Por qué se deshabilita' }}
                                    </label>
                                    <textarea wire:model="motivoDeAcceso" id="motivo-acceso-{{ $usuario->id }}" rows="2"
                                              class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600"></textarea>
                                    @error('motivoDeAcceso') <p class="text-sm text-rose-700">{{ $message }}</p> @enderror
                                    <div class="flex flex-wrap gap-2">
                                        @if ($usuario->estaDeshabilitado())
                                            <x-boton wire:click="habilitar" wire:loading.attr="disabled" class="px-4 py-2.5">Habilitar</x-boton>
                                        @else
                                            <x-boton wire:click="deshabilitar" wire:loading.attr="disabled" class="px-4 py-2.5">Deshabilitar</x-boton>
                                        @endif
                                        <x-boton variante="secundario" type="button" wire:click="abrirAcceso({{ $usuario->id }})" class="px-4 py-2.5">Cancelar</x-boton>
                                    </div>
                                </div>
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
