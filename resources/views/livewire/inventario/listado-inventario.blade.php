<div class="space-y-4">

    @if (session('estado'))
        <p class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ring-1 ring-inset ring-emerald-600/20" role="status">
            {{ session('estado') }}
        </p>
    @endif

    {{-- Filtros --}}
    <x-tarjeta>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="busqueda" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Buscar por nombre</label>
                <input type="search" wire:model.live.debounce.400ms="busqueda" id="busqueda"
                       placeholder="Maniquí, monitor, camilla…"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
            </div>

            <div>
                <label for="tipo" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Tipo</label>
                <select wire:model.live="tipo" id="tipo"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Todos</option>
                    @foreach ($tipos as $unTipo)
                        <option value="{{ $unTipo->value }}">{{ $unTipo->etiqueta() }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="estado" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Estado</label>
                <select wire:model.live="estado" id="estado"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Todos</option>
                    {{-- Lo que la administrativa busca cuando entra a mirar qué hay que revisar. --}}
                    <option value="{{ \App\Livewire\Inventario\ListadoInventario::NO_OPERATIVAS }}">Con unidades no operativas</option>
                    @foreach ($estados as $unEstado)
                        <option value="{{ $unEstado->value }}">Con unidades {{ mb_strtolower($unEstado->etiqueta()) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="fidelidad" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Fidelidad</label>
                <select wire:model.live="fidelidad" id="fidelidad"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Todas</option>
                    @foreach ($nivelesFidelidad as $nivel)
                        <option value="{{ $nivel->value }}">{{ $nivel->etiqueta() }}</option>
                    @endforeach
                    {{-- Los simuladores que esperan que el ADMIN complete el dato. --}}
                    <option value="{{ \App\Livewire\Inventario\ListadoInventario::SIN_ASIGNAR }}">Pendientes de asignar</option>
                </select>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600">
                {{ trans_choice(':count ítem|:count ítems', $items->total(), ['count' => $items->total()]) }}
            </p>
            <div class="flex flex-wrap gap-2">
                <x-boton variante="secundario" type="button" wire:click="limpiarFiltros">Limpiar filtros</x-boton>
                <x-boton variante="secundario" href="{{ route('panel.inventario.disponibilidad') }}">Disponibilidad</x-boton>
                @can('create', \App\Models\ItemInventario::class)
                    <x-boton href="{{ route('panel.inventario.nuevo') }}">Registrar ítem</x-boton>
                @endcan
            </div>
        </div>
    </x-tarjeta>

    @if ($items->isEmpty())
        <x-mensaje-vacio
            titulo="No hay ítems que coincidan"
            descripcion="Prueba a quitar algún filtro o a cambiar la búsqueda."
        />
    @else
        {{-- Tarjetas en móvil: una tabla de seis columnas no se lee a 390 px. --}}
        <ul class="space-y-3 lg:hidden">
            @foreach ($items as $item)
                <li wire:key="item-movil-{{ $item->id }}">
                    <x-tarjeta @class(['opacity-75' => ! $item->activo])>
                        {{-- En columna y no en fila: el desglose puede traer tres
                             etiquetas, y a 390 px dejaban el nombre en «B..». --}}
                        <div class="space-y-2">
                            <p class="text-sm font-semibold text-gray-900">{{ $item->nombre }}</p>
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
                    </x-tarjeta>
                </li>
            @endforeach
        </ul>

        {{-- Tabla en pantalla ancha. --}}
        <div class="hidden lg:block">
            <x-tabla :encabezados="['Nombre', 'Tipo', 'Fidelidad', 'Unidades', 'Desglose', '']">
                @foreach ($items as $item)
                    <tr wire:key="item-tabla-{{ $item->id }}" @class(['opacity-75' => ! $item->activo])>
                        <td class="px-4 py-3 font-medium text-gray-900">{{ $item->nombre }}</td>
                        <td class="px-4 py-3"><x-tipo-de-item :tipo="$item->tipo" /></td>
                        <td class="px-4 py-3"><x-nivel-de-fidelidad :nivel="$item->nivel_fidelidad" :tipo="$item->tipo" /></td>
                        <td class="px-4 py-3 tabular-nums">{{ $item->cantidad_total }}</td>
                        <td class="px-4 py-3"><x-desglose-de-unidades :item="$item" /></td>
                        <td class="px-4 py-3">
                            @include('livewire.inventario.partes.acciones', ['item' => $item, 'compacto' => true])
                        </td>
                    </tr>
                @endforeach
            </x-tabla>
        </div>

        <div>{{ $items->links() }}</div>
    @endif
</div>
