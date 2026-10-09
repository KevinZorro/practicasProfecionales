<div class="space-y-4">

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
            {{ $errorDeRegla }}
        </p>
    @endif

    @if ($abierto)
        <x-tarjeta>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Periodo abierto</p>
            <p class="text-2xl font-semibold text-gray-900">{{ $abierto->nombre }}</p>
            <p class="mt-1 text-sm text-gray-600">
                Abierto el {{ $abierto->abierto_at->format('d/m/Y') }} por {{ $abierto->abiertoPor?->nombre ?? 'desconocido' }}.
            </p>

            <x-slot:pie>
                <div class="flex flex-wrap items-center gap-3">
                    <x-boton variante="secundario" type="button" wire:click="cerrar({{ $abierto->id }})"
                             wire:confirm="¿Cerrar el periodo {{ $abierto->nombre }}? Mientras no se abra otro, no se reciben formatos."
                             class="px-4 py-2.5">
                        Cerrar el periodo
                    </x-boton>
                    <span>Ciérrelo cuando termine el semestre. Lo entregado sigue valiendo hasta que se abra el siguiente.</span>
                </div>
            </x-slot:pie>
        </x-tarjeta>
    @else
        <x-tarjeta titulo="Abrir un periodo">
            @if ($vigente)
                <p class="mb-3 text-sm text-gray-700">
                    No hay ningún periodo abierto. Sigue valiendo <span class="font-medium">{{ $vigente->nombre }}</span>,
                    pero no se reciben formatos nuevos hasta que abra el siguiente.
                </p>
            @else
                <p class="mb-3 text-sm text-gray-700">
                    Todavía no se ha abierto ningún periodo. Hasta que lo abra, nadie puede entregar su formato de confidencialidad.
                </p>
            @endif

            <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700">Nombre del periodo</label>
            <input type="text" wire:model="nombre" id="nombre" placeholder="2026-2" maxlength="20"
                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600 sm:max-w-xs">
            @error('nombre') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror

            <x-slot:pie>
                <div class="flex flex-wrap items-center gap-3">
                    <x-boton type="button" wire:click="abrir" wire:loading.attr="disabled" wire:target="abrir" class="px-4 py-2.5">
                        Abrir el periodo
                    </x-boton>
                    <span>Al abrirlo, todos deben entregar de nuevo el formato de confidencialidad.</span>
                </div>
            </x-slot:pie>
        </x-tarjeta>
    @endif

    <x-tarjeta titulo="Historial">
        @if ($historial->isEmpty())
            <x-mensaje-vacio
                titulo="Todavía no hay periodos"
                descripcion="El primero que abra aparecerá aquí."
            />
        @else
            <ul class="divide-y divide-gray-200">
                @foreach ($historial as $periodo)
                    <li wire:key="periodo-{{ $periodo->id }}" class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ $periodo->nombre }}</p>
                            <p class="text-sm text-gray-600">
                                Abierto el {{ $periodo->abierto_at->format('d/m/Y') }} por {{ $periodo->abiertoPor?->nombre ?? 'desconocido' }}
                                @if ($periodo->cerrado_at)
                                    · cerrado el {{ $periodo->cerrado_at->format('d/m/Y') }} por {{ $periodo->cerradoPor?->nombre ?? 'desconocido' }}
                                @endif
                            </p>
                        </div>

                        @if ($periodo->estaAbierto())
                            <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-900 ring-1 ring-inset ring-emerald-600/20">Abierto</span>
                        @elseif (! $abierto && $vigente?->is($periodo))
                            {{-- Para deshacer un cierre por error: solo el último y sin otro abierto. --}}
                            <x-boton variante="secundario" type="button" wire:click="reabrir({{ $periodo->id }})"
                                     wire:confirm="¿Reabrir el periodo {{ $periodo->nombre }}?" class="px-3 py-2">
                                Reabrir
                            </x-boton>
                        @endif
                    </li>
                @endforeach
            </ul>

            <div class="mt-3">{{ $historial->links() }}</div>
        @endif
    </x-tarjeta>
</div>
