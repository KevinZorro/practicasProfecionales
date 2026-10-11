<div class="space-y-5">

    {{-- Filtros --}}
    <x-tarjeta>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div class="sm:col-span-2">
                <label for="busqueda" class="campo-etiqueta">Buscar por nombre</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.2-5.2M10.5 18a7.5 7.5 0 100-15 7.5 7.5 0 000 15z"/>
                    </svg>
                    <input type="search" wire:model.live.debounce.400ms="busqueda" id="busqueda" placeholder="Maniquí, monitor, camilla…" class="campo pl-9">
                </div>
            </div>

            <div>
                <label for="tipo" class="campo-etiqueta">Tipo</label>
                <select wire:model.live="tipo" id="tipo" class="campo">
                    <option value="">Todos</option>
                    @foreach ($tipos as $unTipo)
                        <option value="{{ $unTipo->value }}">{{ $unTipo->etiqueta() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="estado" class="campo-etiqueta">Estado</label>
                <select wire:model.live="estado" id="estado" class="campo">
                    <option value="">Todos</option>
                    {{-- Lo que la administrativa busca cuando entra a mirar qué hay que revisar. --}}
                    <option value="{{ \App\Livewire\Inventario\ListadoInventario::NO_OPERATIVAS }}">Con unidades no operativas</option>
                    @foreach ($estados as $unEstado)
                        <option value="{{ $unEstado->value }}">Con unidades {{ mb_strtolower($unEstado->etiqueta()) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="fidelidad" class="campo-etiqueta">Fidelidad</label>
                <select wire:model.live="fidelidad" id="fidelidad" class="campo">
                    <option value="">Todas</option>
                    @foreach ($nivelesFidelidad as $nivel)
                        <option value="{{ $nivel->value }}">{{ $nivel->etiqueta() }}</option>
                    @endforeach
                    {{-- Los simuladores que esperan que el ADMIN complete el dato. --}}
                    <option value="{{ \App\Livewire\Inventario\ListadoInventario::SIN_ASIGNAR }}">Pendientes de asignar</option>
                </select>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
            <p class="text-[14px] text-gray-500">
                {{ trans_choice(':count ítem|:count ítems', $items->total(), ['count' => $items->total()]) }}
            </p>
            <x-boton variante="texto" tamano="chico" type="button" wire:click="limpiarFiltros">Limpiar filtros</x-boton>
        </div>
    </x-tarjeta>

    @if ($items->isEmpty())
        <x-mensaje-vacio
            titulo="No hay ítems que coincidan"
            descripcion="Prueba a quitar algún filtro o a cambiar la búsqueda."
        />
    @else
        {{-- Tarjetas en móvil: una tabla de seis columnas no se lee a 390 px. --}}
        <ul class="divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white lg:hidden">
            @foreach ($items as $item)
                <li wire:key="item-movil-{{ $item->id }}" @class(['px-4 py-4', 'opacity-75' => ! $item->activo])>
                        {{-- En columna y no en fila: el desglose puede traer tres
                             etiquetas, y a 390 px dejaban el nombre en «B..». --}}
                        <div class="space-y-2">
                            <p class="text-[15px] font-semibold leading-snug text-gray-900">{{ $item->nombre }}</p>
                            @if ($item->simulador)
                                <p class="text-[13px] text-gray-500">De: {{ $item->simulador->nombre }}</p>
                            @endif
                            <x-desglose-de-unidades :item="$item" />
                        </div>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <x-tipo-de-item :tipo="$item->tipo" />
                            <x-nivel-de-fidelidad :nivel="$item->nivel_fidelidad" :tipo="$item->tipo" />
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-3">
                            <x-dato etiqueta="Unidades">{{ $item->cantidad_total }}</x-dato>
                            <x-dato etiqueta="Cuentan como disponibles">
                                @if ($item->activo && $item->cantidad_operativa > 0)
                                    {{ $item->cantidad_operativa }} de {{ $item->cantidad_total }}
                                @else
                                    <span class="text-amber-800">Ninguna</span>
                                @endif
                            </x-dato>
                        </dl>
                        @include('livewire.inventario.partes.acciones', ['item' => $item])
                </li>
            @endforeach
        </ul>

        {{-- Tabla en pantalla ancha. --}}
        <div class="hidden lg:block">
            <x-tabla :encabezados="['Nombre', 'Tipo', 'Fidelidad', 'Unidades', 'Desglose', '']">
                @foreach ($items as $item)
                    <tr wire:key="item-tabla-{{ $item->id }}" @class(['opacity-75' => ! $item->activo])>
                        <td class="font-semibold text-gray-900">
                            {{ $item->nombre }}
                            @if ($item->simulador)
                                <span class="block text-[13px] font-normal text-gray-500">De: {{ $item->simulador->nombre }}</span>
                            @endif
                        </td>
                        <td><x-tipo-de-item :tipo="$item->tipo" /></td>
                        <td><x-nivel-de-fidelidad :nivel="$item->nivel_fidelidad" :tipo="$item->tipo" /></td>
                        <td>{{ $item->cantidad_total }}</td>
                        <td><x-desglose-de-unidades :item="$item" /></td>
                        <td>
                            @include('livewire.inventario.partes.acciones', ['item' => $item, 'compacto' => true])
                        </td>
                    </tr>
                @endforeach
            </x-tabla>
        </div>

        <div>{{ $items->links() }}</div>
    @endif
</div>
