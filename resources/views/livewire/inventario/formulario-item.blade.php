<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">
            {{ $errorDeRegla }}
        </x-aviso>
    @endif

    <x-tarjeta titulo="Datos del ítem">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="nombre" class="campo-etiqueta">Nombre</label>
                <input type="text" wire:model="nombre" id="nombre"
                       class="campo">
                @error('nombre') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="tipo" class="campo-etiqueta">Tipo</label>
                <select wire:model.live="tipo" id="tipo"
                        class="campo">
                    @foreach ($tipos as $unTipo)
                        <option value="{{ $unTipo->value }}">{{ $unTipo->etiqueta() }}</option>
                    @endforeach
                </select>
                @error('tipo') <p class="campo-error">{{ $message }}</p> @enderror
            </div>

            @if ($esAccesorio)
                <div>
                    <label for="simuladorId" class="campo-etiqueta">De qué simulador</label>
                    <select wire:model="simuladorId" id="simuladorId"
                            class="campo">
                        <option value="">Elige el simulador</option>
                        @foreach ($simuladores as $simulador)
                            <option value="{{ $simulador->id }}">{{ $simulador->nombre }}</option>
                        @endforeach
                    </select>
                    @error('simuladorId') <p class="campo-error">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label for="cantidadTotal" class="campo-etiqueta">Unidades</label>
                @if ($esAlta)
                    <input type="number" min="0" wire:model="cantidadTotal" id="cantidadTotal"
                           class="campo">
                    @error('cantidadTotal') <p class="campo-error">{{ $message }}</p> @enderror
                    <p class="campo-ayuda">Todas entran operativas.</p>
                @else
                    <p class="campo bg-gray-50 text-gray-700">{{ $item->cantidad_total }}</p>
                    <p class="campo-ayuda">
                        Las unidades se mueven desde el listado, con su cantidad y su motivo.
                    </p>
                @endif
            </div>

            <div>
                <span class="campo-etiqueta">Estado funcional</span>
                @if ($esAlta)
                    <p class="mt-2 text-[14px] text-gray-600">Todas nacen operativas.</p>
                @else
                    <x-desglose-de-unidades :item="$item" class="mt-2" />
                @endif
            </div>

            <div class="flex items-end">
                <label class="flex w-full cursor-pointer items-center gap-3 rounded-[10px] border border-gray-300 px-3 py-2.5 hover:border-gray-400">
                    <input type="checkbox" wire:model="activo" class="casilla">
                    <span class="text-[15px] text-gray-900">Activo en el inventario</span>
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
                    <label for="nivelFidelidad" class="campo-etiqueta">Nivel de fidelidad</label>

                    @if ($puedeEditarFidelidad)
                        <select wire:model="nivelFidelidad" id="nivelFidelidad"
                                class="campo">
                            <option value="">Pendiente de asignar</option>
                            @foreach ($nivelesFidelidad as $nivel)
                                <option value="{{ $nivel->value }}">{{ $nivel->etiqueta() }}</option>
                            @endforeach
                        </select>
                    @else
                        <div class="flex flex-wrap items-center gap-2 rounded-[10px] bg-gray-50 px-3 py-2.5">
                            <x-nivel-de-fidelidad
                                :nivel="\App\Enums\NivelFidelidad::tryFrom($nivelFidelidad)"
                                :tipo="\App\Enums\TipoItemInventario::Simulador"
                            />
                        </div>
                        <p class="campo-ayuda">
                            El nivel de fidelidad solo lo registra el administrador de la plataforma.
                            Puedes editar el resto de campos de este simulador con normalidad.
                        </p>
                    @endif
                </div>
            @endif

            <div class="sm:col-span-2">
                <label for="descripcion" class="campo-etiqueta">Descripción</label>
                <textarea wire:model="descripcion" id="descripcion" rows="3"
                          class="campo"></textarea>
                @error('descripcion') <p class="campo-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </x-tarjeta>

    <div class="flex flex-wrap items-center gap-2">
        <x-boton type="button" wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">Guardar</x-boton>
        <x-boton variante="texto" href="{{ route('panel.inventario') }}">Cancelar</x-boton>
    </div>
</div>
