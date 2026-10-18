<div class="space-y-5">
    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">{{ $errorDeRegla }}</x-aviso>
    @endif

    @unless ($supervisor->hasRole(\App\Enums\Rol::Supervisor->value))
        <x-aviso tono="atencion">
            {{ $supervisor->nombre }} no tiene vigente el rol de docente supervisor. Asígnaselo en Usuarios y roles para poder ponerle docentes a cargo.
        </x-aviso>
    @endunless

    <x-tarjeta titulo="Docentes a cargo de {{ $supervisor->nombre }}">
        @if ($docentes->isEmpty())
            <p class="text-[14px] text-gray-500">Todavía no tiene docentes a cargo. Elige abajo a quién asignarle.</p>
        @else
            <ul class="-my-2 divide-y divide-gray-100">
                @foreach ($docentes as $docente)
                    <li wire:key="supervisado-{{ $docente->id }}" class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-[15px] font-semibold leading-snug text-gray-900">{{ $docente->nombre }}</p>
                            <p class="truncate text-[13px] text-gray-500">{{ $docente->email }}</p>
                        </div>
                        <x-boton variante="texto" tamano="chico" type="button" wire:click="retirar({{ $docente->id }})"
                                 wire:loading.attr="disabled"
                                 wire:confirm="¿Retirar a {{ $docente->nombre }} de los docentes de {{ $supervisor->nombre }}? Deja de ver sus evaluaciones en el acto."
                                 class="text-rojo-700 hover:text-rojo-800">Retirar</x-boton>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-tarjeta>

    <x-tarjeta titulo="Asignar un docente">
        <label for="buscar-docente" class="sr-only">Buscar un docente por nombre o correo</label>
        <input type="search" wire:model.live.debounce.400ms="busqueda" id="buscar-docente" placeholder="Nombre o correo" class="campo">

        @if ($candidatos->isEmpty())
            <p class="mt-3 text-[14px] text-gray-500">No hay docentes que coincidan o todos ya están a su cargo.</p>
        @else
            <ul class="mt-3 divide-y divide-gray-100">
                @foreach ($candidatos as $candidato)
                    <li wire:key="candidato-{{ $candidato->id }}" class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-[15px] font-semibold leading-snug text-gray-900">{{ $candidato->nombre }}</p>
                            <p class="truncate text-[13px] text-gray-500">{{ $candidato->email }}</p>
                        </div>
                        <x-boton variante="secundario" tamano="chico" type="button" wire:click="asignar({{ $candidato->id }})" wire:loading.attr="disabled">Asignar</x-boton>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-tarjeta>
</div>
