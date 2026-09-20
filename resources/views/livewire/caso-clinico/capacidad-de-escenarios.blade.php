<div class="space-y-4">

    @if (session('estado'))
        <p class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ring-1 ring-inset ring-emerald-600/20" role="status">
            {{ session('estado') }}
        </p>
    @endif

    <x-tarjeta>
        <label for="busqueda" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Buscar escenario</label>
        <input type="search" wire:model.live.debounce.400ms="busqueda" id="busqueda"
               placeholder="Parto, trauma, morfofisiología…"
               class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">

        <p class="mt-3 text-sm text-gray-600">
            Cuántos estudiantes admite cada escenario a la vez. Un escenario sin capacidad
            registrada no limita las solicitudes hasta que se le asigne una.
        </p>
    </x-tarjeta>

    @forelse ($casos as $caso)
        <x-tarjeta wire:key="caso-{{ $caso->id }}">
            <div class="sm:flex sm:items-end sm:justify-between sm:gap-4">
                <div class="min-w-0">
                    <h3 class="font-semibold text-gray-900">{{ $caso->nombre }}</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        @if ($caso->capacidad_maxima_estudiantes === null)
                            <span class="text-amber-700">Sin definir</span>
                        @else
                            Admite {{ $caso->capacidad_maxima_estudiantes }} estudiantes
                        @endif
                    </p>
                </div>

                <div class="mt-3 flex items-end gap-2 sm:mt-0">
                    <div>
                        <label for="capacidad-{{ $caso->id }}" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">
                            Capacidad máxima
                        </label>
                        <input type="number" min="1" max="200" inputmode="numeric"
                               wire:model="capacidades.{{ $caso->id }}" id="capacidad-{{ $caso->id }}"
                               class="w-28 rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    </div>

                    <x-boton wire:click="guardar({{ $caso->id }})" type="button">Guardar</x-boton>
                </div>
            </div>

            @error('capacidades.'.$caso->id)
                <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
            @enderror
        </x-tarjeta>
    @empty
        <x-mensaje-vacio titulo="No hay escenarios clínicos que coincidan con la búsqueda." />
    @endforelse

    <div>{{ $casos->links() }}</div>
</div>
