<div class="space-y-4">

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
            {{ $errorDeRegla }}
        </p>
    @endif

    @if (! $esAlta && $cuenta->origen->vieneDeLaSincronizacion())
        <p class="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-inset ring-amber-600/20">
            Esta cuenta viene de la base institucional. Lo que cambies aquí lo volverá a escribir
            la siguiente sincronización: si un dato está mal, hay que corregirlo en la universidad.
        </p>
    @endif

    <x-tarjeta titulo="Datos de la cuenta">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700">Nombre completo</label>
                <input type="text" wire:model="nombre" id="nombre"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                @error('nombre') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="correo" class="mb-1 block text-sm font-medium text-gray-700">Correo institucional</label>
                <input type="email" wire:model="correo" id="correo" autocomplete="off"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                <p class="mt-1 text-sm text-gray-600">Con este correo entra por Google. No puede repetirse.</p>
                @error('correo') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="documento" class="mb-1 block text-sm font-medium text-gray-700">Documento (opcional)</label>
                <input type="text" wire:model="documento" id="documento" inputmode="numeric"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                @error('documento') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="codigoInstitucional" class="mb-1 block text-sm font-medium text-gray-700">Código institucional (opcional)</label>
                <input type="text" wire:model="codigoInstitucional" id="codigoInstitucional"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                @error('codigoInstitucional') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="programa" class="mb-1 block text-sm font-medium text-gray-700">Programa (opcional)</label>
                <select wire:model="programa" id="programa"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Sin programa</option>
                    @foreach ($programas as $unPrograma)
                        <option value="{{ $unPrograma }}">{{ $unPrograma }}</option>
                    @endforeach
                </select>
                @error('programa') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
        </div>
    </x-tarjeta>

    <div class="flex flex-wrap gap-2">
        <x-boton wire:click="guardar" wire:loading.attr="disabled" class="px-4 py-2.5">Guardar</x-boton>
        <x-boton variante="secundario" href="{{ route('panel.usuarios') }}" class="px-4 py-2.5">Cancelar</x-boton>
    </div>
</div>
