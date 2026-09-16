<div class="space-y-4">

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
            {{ $errorDeRegla }}
        </p>
    @endif

    <x-tarjeta titulo="Datos del ítem">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="nombre" class="mb-1 block text-sm font-medium text-gray-700">Nombre</label>
                <input type="text" wire:model="nombre" id="nombre"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                @error('nombre') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="tipo" class="mb-1 block text-sm font-medium text-gray-700">Tipo</label>
                <select wire:model.live="tipo" id="tipo"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    @foreach ($tipos as $unTipo)
                        <option value="{{ $unTipo->value }}">{{ $unTipo->etiqueta() }}</option>
                    @endforeach
                </select>
                @error('tipo') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="cantidadTotal" class="mb-1 block text-sm font-medium text-gray-700">Unidades</label>
                <input type="number" min="0" wire:model="cantidadTotal" id="cantidadTotal"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                @error('cantidadTotal') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="estado" class="mb-1 block text-sm font-medium text-gray-700">Estado</label>
                <select wire:model="estado" id="estado"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    @foreach ($estados as $unEstado)
                        <option value="{{ $unEstado->value }}">{{ $unEstado->etiqueta() }}</option>
                    @endforeach
                </select>
                @error('estado') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-end">
                <label class="flex cursor-pointer items-center gap-3 rounded-md border border-gray-200 px-3 py-2.5">
                    <input type="checkbox" wire:model="activo"
                           class="size-5 rounded border-gray-300 text-sky-700 focus:ring-sky-600">
                    <span class="text-sm text-gray-900">Activo en el inventario</span>
                </label>
            </div>

            {{--
                Nivel de fidelidad (RF39). Se enseña siempre que el ítem sea
                un simulador, pero solo el ADMIN lo edita. A los demás se les
                muestra el valor con la razón escrita: ocultarlo les haría
                creer que el dato no existe.
            --}}
            @if ($esSimulador)
                <div class="sm:col-span-2">
                    <label for="nivelFidelidad" class="mb-1 block text-sm font-medium text-gray-700">Nivel de fidelidad</label>

                    @if ($puedeEditarFidelidad)
                        <select wire:model="nivelFidelidad" id="nivelFidelidad"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                            <option value="">Pendiente de asignar</option>
                            @foreach ($nivelesFidelidad as $nivel)
                                <option value="{{ $nivel->value }}">{{ $nivel->etiqueta() }}</option>
                            @endforeach
                        </select>
                    @else
                        <div class="flex flex-wrap items-center gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5">
                            <x-nivel-de-fidelidad
                                :nivel="\App\Enums\NivelFidelidad::tryFrom($nivelFidelidad)"
                                :tipo="\App\Enums\TipoItemInventario::Simulador"
                            />
                        </div>
                        <p class="mt-1 text-sm text-gray-600">
                            El nivel de fidelidad solo lo registra el administrador de la plataforma.
                            Puedes editar el resto de campos de este simulador con normalidad.
                        </p>
                    @endif
                </div>
            @endif

            <div class="sm:col-span-2">
                <label for="descripcion" class="mb-1 block text-sm font-medium text-gray-700">Descripción</label>
                <textarea wire:model="descripcion" id="descripcion" rows="3"
                          class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600"></textarea>
                @error('descripcion') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
            </div>
        </div>
    </x-tarjeta>

    <div class="flex flex-wrap gap-2">
        <x-boton wire:click="guardar" wire:loading.attr="disabled" class="px-4 py-2.5">Guardar</x-boton>
        <x-boton variante="secundario" href="{{ route('panel.inventario') }}" class="px-4 py-2.5">Cancelar</x-boton>
    </div>
</div>
