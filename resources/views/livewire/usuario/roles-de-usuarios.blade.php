<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">
            {{ $errorDeRegla }}
        </x-aviso>
    @endif

    {{-- Próximos a vencer: el aviso que evita que el pasante se quede fuera
         sin que nadie lo hubiera previsto (RF63, RF64). --}}
    @if ($proximosAVencer->isNotEmpty())
        <x-aviso tono="atencion">
            <p class="font-semibold">Vencen en los próximos {{ $diasDeAviso }} días</p>
            <ul class="mt-1 space-y-0.5">
                @foreach ($proximosAVencer as $porVencer)
                    @foreach ($porVencer->roles as $role)
                        <li wire:key="vence-{{ $porVencer->id }}-{{ $role->id }}">
                            {{ $porVencer->nombre }}: {{ \App\Enums\Rol::from($role->name)->etiqueta() }}
                            hasta el {{ $role->pivot->hasta->format('d/m/Y') }}
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </x-aviso>
    @endif

    <div class="flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1 basis-64">
            <label for="buscar" class="sr-only">Buscar por nombre, correo o código</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.2-5.2M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/>
                </svg>
                <input type="search" wire:model.live.debounce.400ms="busqueda" id="buscar" placeholder="Nombre, correo o código" class="campo pl-9">
            </div>
        </div>
        {{-- RF22: la mayoría llegan de la sincronización; aquí, quien no figura en ella. --}}
        @can('create', \App\Models\User::class)
            <x-boton href="{{ route('panel.usuarios.nueva') }}">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Nueva cuenta
            </x-boton>
        @endcan
    </div>

    <p class="text-[14px] text-gray-500">
        {{ trans_choice(':count persona|:count personas', $usuarios->total(), ['count' => $usuarios->total()]) }}
    </p>

    @if ($usuarios->isEmpty())
        <x-mensaje-vacio titulo="No hay nadie que coincida" descripcion="Prueba con otro nombre o código." />
    @else
        <ul class="divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white">
            @foreach ($usuarios as $usuario)
                <li wire:key="usuario-{{ $usuario->id }}" class="space-y-3 px-4 py-4 sm:px-5">
                    <div class="flex min-w-0 items-start gap-3">
                        <x-iniciales :nombre="$usuario->nombre" class="mt-0.5 size-9 bg-gray-100 text-[13px] text-gray-700" />
                        {{-- Columna y no fila: a 390 px el nombre y las etiquetas
                             no caben juntos y el nombre se corta. --}}
                        <div class="min-w-0">
                            <p class="text-[15px] font-semibold leading-snug text-gray-900">{{ $usuario->nombre }}</p>
                            <p class="truncate text-[14px] text-gray-500">{{ $usuario->email }}</p>
                            <p class="text-[13px] text-gray-500">
                                {{ collect([$usuario->origen->etiqueta(), $usuario->programa])->filter()->implode(' · ') }}
                            </p>
                        </div>
                    </div>

                    {{-- Dos motivos distintos para no entrar: la vigencia institucional
                         (la escribe la sincronización) y la marca del ADMIN (RF22). --}}
                    @if ($usuario->estaDeshabilitado())
                        <x-aviso tono="peligro">
                            Deshabilitada el {{ $usuario->deshabilitado_at->format('d/m/Y') }}: {{ $usuario->motivo_deshabilitacion }}
                        </x-aviso>
                    @elseif ($usuario->estado === \App\Enums\EstadoUsuario::Inactivo)
                        <x-aviso>Sin vigencia institucional: no puede entrar.</x-aviso>
                    @endif

                    @if ($usuario->roles->isEmpty())
                        <p class="text-[14px] text-gray-500">Sin ningún rol asignado.</p>
                    @else
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($usuario->roles as $role)
                                @php($rol = \App\Enums\Rol::from($role->name))
                                <li class="inline-flex items-center gap-1 rounded-full bg-gray-100 py-0.5 pl-3 pr-1 text-[13px] font-semibold text-gray-800" wire:key="rol-{{ $usuario->id }}-{{ $role->id }}">
                                    {{ $rol->etiqueta() }}
                                    @if ($role->pivot->hasta)
                                        <span class="font-medium text-amber-800">· temporal hasta {{ $role->pivot->hasta->format('d/m/Y') }}</span>
                                    @endif

                                    @if (! ($rol === \App\Enums\Rol::Admin && $usuario->is(auth()->user())))
                                    @can('revocar', \App\Models\AsignacionDeRol::class)
                                        <button type="button" wire:click="revocar({{ $usuario->id }}, '{{ $rol->value }}')"
                                                wire:loading.attr="disabled" wire:confirm="¿Revocar el rol {{ $rol->etiqueta() }} a {{ $usuario->nombre }}? Deja de tener efecto en el acto."
                                                class="ml-1 rounded-full px-2 py-1 text-[12px] font-semibold text-rojo-700 hover:bg-rojo-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-rojo">
                                            Revocar
                                        </button>
                                    @endcan
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="flex flex-wrap items-center gap-2">
                        @can('asignar', \App\Models\AsignacionDeRol::class)
                            @unless ($asignandoA === $usuario->id)
                                <x-boton variante="secundario" tamano="chico" type="button" wire:click="abrir({{ $usuario->id }})">Asignar un rol</x-boton>
                            @endunless
                        @endcan
                        @can('update', $usuario)
                            <x-boton variante="texto" tamano="chico" href="{{ route('panel.usuarios.editar', $usuario) }}" wire:navigate>Editar datos</x-boton>
                        @endcan
                        @can('gestionar', \App\Models\Supervision::class)
                            @if ($usuario->roles->contains('name', \App\Enums\Rol::Supervisor->value))
                                <x-boton variante="texto" tamano="chico" href="{{ route('panel.usuarios.supervision', $usuario) }}" wire:navigate>Docentes a su cargo</x-boton>
                            @endif
                        @endcan
                        @if ($usuario->estaDeshabilitado())
                            @can('habilitar', $usuario)
                                <x-boton variante="texto" tamano="chico" type="button" wire:click="abrirAcceso({{ $usuario->id }})">Volver a habilitar</x-boton>
                            @endcan
                        @else
                            @can('deshabilitar', $usuario)
                                <x-boton variante="texto" tamano="chico" type="button" wire:click="abrirAcceso({{ $usuario->id }})" class="text-rojo-700 hover:text-rojo-800">Deshabilitar</x-boton>
                            @endcan
                        @endif
                    </div>

                    @if ($cambiandoAcceso === $usuario->id)
                        <div class="space-y-3 rounded-xl bg-gray-50 p-3.5">
                            <div>
                                <label for="motivo-acceso-{{ $usuario->id }}" class="campo-etiqueta">
                                    {{ $usuario->estaDeshabilitado() ? 'Por qué se vuelve a habilitar' : 'Por qué se deshabilita' }}
                                </label>
                                <textarea wire:model="motivoDeAcceso" id="motivo-acceso-{{ $usuario->id }}" rows="2" class="campo" x-init="$el.focus()"></textarea>
                                @error('motivoDeAcceso') <p class="campo-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if ($usuario->estaDeshabilitado())
                                    <x-boton tamano="chico" type="button" wire:click="habilitar" wire:loading.attr="disabled">Habilitar</x-boton>
                                @else
                                    <x-boton variante="peligro" tamano="chico" type="button" wire:click="deshabilitar" wire:loading.attr="disabled">Deshabilitar</x-boton>
                                @endif
                                <x-boton variante="secundario" tamano="chico" type="button" wire:click="abrirAcceso({{ $usuario->id }})">Cancelar</x-boton>
                            </div>
                        </div>
                    @endif

                    @can('asignar', \App\Models\AsignacionDeRol::class)
                        @if ($asignandoA === $usuario->id)
                            <div class="space-y-4 rounded-xl bg-gray-50 p-3.5">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="rol-{{ $usuario->id }}" class="campo-etiqueta">Rol</label>
                                        <select wire:model.live="rolElegido" id="rol-{{ $usuario->id }}" class="campo">
                                            <option value="">Elige un rol</option>
                                            @foreach ($rolesAsignables as $asignable)
                                                <option value="{{ $asignable->value }}">{{ $asignable->etiqueta() }}</option>
                                            @endforeach
                                        </select>
                                        @unless (in_array(\App\Enums\Rol::Coordinador, $rolesAsignables, true))
                                            <p class="campo-ayuda">El rol de Coordinador no se asigna a estudiantes.</p>
                                        @endunless
                                        @error('rolElegido') <p class="campo-error">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="hasta-{{ $usuario->id }}" class="campo-etiqueta">Hasta <span class="font-normal text-gray-500">(opcional)</span></label>
                                        <input type="date" wire:model="hasta" id="hasta-{{ $usuario->id }}" class="campo">
                                        <p class="campo-ayuda">Vale todo ese día y deja de tener efecto al día siguiente. Vacío: permanente.</p>
                                        @error('hasta') <p class="campo-error">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                {{-- RF63: delegar la facultad de aprobar exige justificación. --}}
                                @if ($rolElegido === \App\Enums\Rol::Coordinador->value)
                                    <div>
                                        <label for="motivo-{{ $usuario->id }}" class="campo-etiqueta">Motivo de la delegación</label>
                                        <textarea wire:model="motivo" id="motivo-{{ $usuario->id }}" rows="2" class="campo"></textarea>
                                        @error('motivo') <p class="campo-error">{{ $message }}</p> @enderror
                                        <p class="campo-ayuda">Obligatorio al elevar a coordinador.</p>
                                    </div>
                                @endif

                                <div class="flex flex-wrap gap-2">
                                    <x-boton tamano="chico" type="button" wire:click="asignar" wire:loading.attr="disabled">Asignar</x-boton>
                                    <x-boton variante="secundario" tamano="chico" type="button" wire:click="cerrar">Cancelar</x-boton>
                                </div>
                            </div>
                        @endif
                    @endcan
                </li>
            @endforeach
        </ul>

        <div>{{ $usuarios->links() }}</div>
    @endif
</div>
