<div class="space-y-4">

    <x-tarjeta titulo="Cargar una nueva plantilla">
        @if ($errorDeRegla)
            <p class="mb-3 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
                {{ $errorDeRegla }}
            </p>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700">Nombre</label>
                <input type="text" wire:model="nombre" id="nombre"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                @error('nombre') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="version" class="mb-1 block text-sm font-medium text-gray-700">Versión</label>
                <input type="text" wire:model="version" id="version" placeholder="2026.1"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                @error('version') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="archivo" class="mb-1 block text-sm font-medium text-gray-700">Archivo PDF</label>
                <input type="file" wire:model="archivo" id="archivo" accept="application/pdf"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm">
                @error('archivo') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                <div wire:loading wire:target="archivo" class="mt-1 text-sm text-sky-800">Subiendo…</div>
            </div>
        </div>

        <x-slot:pie>
            <div class="flex flex-wrap items-center gap-3">
                <x-boton wire:click="cargar" wire:loading.attr="disabled" wire:target="cargar,archivo" class="px-4 py-2.5">Cargar plantilla</x-boton>
                <span class="text-sm">Al cargarla, la anterior deja de estar vigente automáticamente.</span>
            </div>
        </x-slot:pie>
    </x-tarjeta>

    <x-tarjeta titulo="Versiones">
        @if ($plantillas->isEmpty())
            <x-mensaje-vacio
                titulo="Todavía no hay ninguna plantilla"
                descripcion="Hasta que cargues una, los estudiantes no podrán entregar su consentimiento."
            />
        @else
            <ul class="space-y-2">
                @foreach ($plantillas as $plantilla)
                    <li wire:key="plantilla-{{ $plantilla->id }}"
                        @class(['rounded-md border px-3 py-3', 'border-emerald-300 bg-emerald-50' => $plantilla->activo, 'border-gray-200' => ! $plantilla->activo])>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">{{ $plantilla->nombre }}</p>
                                <p class="text-sm text-gray-600">
                                    Versión {{ $plantilla->version }} · cargada por {{ $plantilla->subidoPor?->nombre ?? 'desconocido' }}
                                    el {{ $plantilla->created_at->format('d/m/Y') }}
                                </p>
                            </div>
                            @if ($plantilla->activo)
                                <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-900 ring-1 ring-inset ring-emerald-600/20">
                                    Vigente
                                </span>
                            @else
                                <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/20">
                                    Retirada
                                </span>
                            @endif
                        </div>
                        <x-boton variante="secundario" href="{{ route('panel.consentimientos.version', $plantilla) }}" class="mt-2 px-3 py-2">
                            Descargar
                        </x-boton>
                    </li>
                @endforeach
            </ul>

            <div class="mt-3">{{ $plantillas->links() }}</div>
        @endif
    </x-tarjeta>
</div>
