<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">{{ $errorDeRegla }}</x-aviso>
    @endif

    @if ($abierto)
        <x-tarjeta>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <dl>
                    <x-dato etiqueta="Periodo abierto">
                        <span class="text-[1.75rem] font-extrabold leading-tight tracking-[-0.025em]">{{ $abierto->nombre }}</span>
                    </x-dato>
                </dl>
                <x-insignia tono="exito">Recibe formatos</x-insignia>
            </div>
            <p class="mt-2 text-[14px] text-gray-500">
                Abierto el {{ $abierto->abierto_at->format('d/m/Y') }} por {{ $abierto->abiertoPor?->nombre ?? 'desconocido' }}.
            </p>

            <x-slot:pie>
                <x-boton variante="secundario" type="button" wire:click="cerrar({{ $abierto->id }})"
                         wire:confirm="¿Cerrar el periodo {{ $abierto->nombre }}? Mientras no se abra otro, no se reciben formatos.">
                    Cerrar el periodo
                </x-boton>
                <span class="min-w-0 flex-1 basis-64">Ciérralo cuando termine el semestre. Lo entregado sigue valiendo hasta que se abra el siguiente.</span>
            </x-slot:pie>
        </x-tarjeta>
    @else
        <x-tarjeta titulo="Abrir un periodo">
            @if ($vigente)
                <x-aviso tono="atencion" class="mb-4">
                    No hay ningún periodo abierto. Sigue valiendo <span class="font-semibold">{{ $vigente->nombre }}</span>,
                    pero no se reciben formatos nuevos hasta que abras el siguiente.
                </x-aviso>
            @else
                <x-aviso tono="atencion" class="mb-4">
                    Todavía no se ha abierto ningún periodo. Hasta que lo abras, nadie puede entregar su formato de confidencialidad.
                </x-aviso>
            @endif

            <form wire:submit="abrir">
                <label for="nombre" class="campo-etiqueta">Nombre del periodo</label>
                <input type="text" wire:model="nombre" id="nombre" placeholder="2026-2" maxlength="20" class="campo sm:max-w-xs">
                @error('nombre') <p class="campo-error">{{ $message }}</p> @enderror
            </form>

            <x-slot:pie>
                <x-boton type="button" wire:click="abrir" wire:loading.attr="disabled" wire:target="abrir">
                    Abrir el periodo
                </x-boton>
                <span class="min-w-0 flex-1 basis-64">Al abrirlo, todos deben entregar de nuevo el formato de confidencialidad.</span>
            </x-slot:pie>
        </x-tarjeta>
    @endif

    <x-tarjeta titulo="Historial">
        @if ($historial->isEmpty())
            <p class="text-[14px] text-gray-500">Todavía no hay periodos. El primero que abras aparecerá aquí.</p>
        @else
            <ul class="-my-1 divide-y divide-gray-100">
                @foreach ($historial as $periodo)
                    <li wire:key="periodo-{{ $periodo->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-[15px] font-semibold text-gray-900">{{ $periodo->nombre }}</p>
                            <p class="text-[14px] leading-snug text-gray-500">
                                Abierto el {{ $periodo->abierto_at->format('d/m/Y') }} por {{ $periodo->abiertoPor?->nombre ?? 'desconocido' }}
                                @if ($periodo->cerrado_at)
                                    · cerrado el {{ $periodo->cerrado_at->format('d/m/Y') }} por {{ $periodo->cerradoPor?->nombre ?? 'desconocido' }}
                                @endif
                            </p>
                        </div>

                        @if ($periodo->estaAbierto())
                            <x-insignia tono="exito">Abierto</x-insignia>
                        @elseif (! $abierto && $vigente?->is($periodo))
                            {{-- Para deshacer un cierre por error: solo el último y sin otro abierto. --}}
                            <x-boton variante="secundario" tamano="chico" type="button" wire:click="reabrir({{ $periodo->id }})"
                                     wire:confirm="¿Reabrir el periodo {{ $periodo->nombre }}?">
                                Reabrir
                            </x-boton>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $historial->links() }}</div>
        @endif
    </x-tarjeta>
</div>
