<div class="space-y-5">

    <x-tarjeta titulo="Cargar una nueva plantilla">
        @if ($errorDeRegla)
            <x-aviso tono="peligro" role="alert">
                {{ $errorDeRegla }}
            </x-aviso>
        @endif

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="nombre" class="campo-etiqueta">Nombre</label>
                <input type="text" wire:model="nombre" id="nombre"
                       class="campo">
                @error('nombre') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="version" class="campo-etiqueta">Versión</label>
                <input type="text" wire:model="version" id="version" placeholder="2026.1"
                       class="campo">
                @error('version') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="archivo" class="campo-etiqueta">Archivo PDF</label>
                <input type="file" wire:model="archivo" id="archivo" accept="application/pdf" class="campo-archivo">
                @error('archivo') <p class="campo-error">{{ $message }}</p> @enderror
                <p wire:loading wire:target="archivo" class="campo-ayuda font-medium text-gray-700">Subiendo…</p>
            </div>
        </div>

        <x-slot:pie>
            <x-boton type="button" wire:click="cargar" wire:loading.attr="disabled" wire:target="cargar,archivo">Cargar plantilla</x-boton>
            <span class="min-w-0 flex-1 basis-56">Al cargarla, la anterior deja de estar vigente automáticamente.</span>
        </x-slot:pie>
    </x-tarjeta>

    <x-tarjeta titulo="Versiones">
        @if ($plantillas->isEmpty())
            <x-mensaje-vacio
                titulo="Todavía no hay ninguna plantilla"
                descripcion="Hasta que cargues una, nadie podrá entregar su formato."
            />
        @else
            <ul class="-my-1 divide-y divide-gray-100">
                @foreach ($plantillas as $plantilla)
                    <li wire:key="plantilla-{{ $plantilla->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="flex min-w-0 flex-1 flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-[15px] font-semibold text-gray-900">{{ $plantilla->nombre }}</p>
                                <p class="text-[14px] text-gray-500">
                                    Versión {{ $plantilla->version }} · cargada por {{ $plantilla->subidoPor?->nombre ?? 'desconocido' }}
                                    el {{ $plantilla->created_at->format('d/m/Y') }}
                                </p>
                            </div>
                            @if ($plantilla->activo)
                                <x-insignia tono="exito">Vigente</x-insignia>
                            @else
                                <x-insignia tono="neutro">Retirada</x-insignia>
                            @endif
                        </div>
                        <x-boton variante="secundario" tamano="chico" href="{{ route('panel.formatos-confidencialidad.version', $plantilla) }}">
                            Descargar
                        </x-boton>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4">{{ $plantillas->links() }}</div>
        @endif
    </x-tarjeta>
</div>
