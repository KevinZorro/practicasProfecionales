<div class="space-y-4">

    @if (session('estado'))
        <p class="rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ring-1 ring-inset ring-emerald-600/20" role="status">
            {{ session('estado') }}
        </p>
    @endif

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
            {{ $errorDeRegla }}
        </p>
    @endif

    <x-tarjeta>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label for="desde" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Desde</label>
                @if ($origenFijado === null)
                    <input type="date" wire:model.live="desde" id="desde" @disabled($lista !== null)
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    <p class="mt-1 text-xs text-gray-500">La primera lista elige desde cuándo cuenta. Después arranca sola.</p>
                @else
                    <p class="rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5 text-base text-gray-900">
                        {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500">Donde terminó la última lista cerrada: así ningún movimiento queda fuera ni por duplicado.</p>
                @endif
            </div>
            <div>
                <label for="hasta" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Hasta</label>
                <input type="date" wire:model.live="hasta" id="hasta" @disabled($lista !== null)
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
            </div>
        </div>

        @if ($lista)
            <p class="mt-3 text-sm text-gray-700">
                Lista cerrada por {{ $lista->cerradaPor?->nombre ?? 'el laboratorio' }}
                el {{ $lista->cerrada_at->format('d/m/Y H:i') }}. Sus cifras ya no cambian.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-boton href="{{ route('panel.reposicion.excel', $lista) }}">Descargar Excel</x-boton>
                <x-boton variante="secundario" href="{{ route('panel.reposicion.pdf', $lista) }}">Descargar PDF</x-boton>
            </div>
        @else
            <p class="mt-3 text-sm text-gray-600">
                Previsualización del periodo. Todavía no se ha cerrado nada: mientras siga en
                borrador, estas cifras cambian con el inventario.
            </p>
        @endif
    </x-tarjeta>

    {{-- Lo que hay que pedir --}}
    @php($filas = $lista ? $lineas : $previsualizacion)

    @if ($filas->isEmpty())
        <x-mensaje-vacio
            titulo="No hay nada que pedir en este periodo"
            descripcion="Ni salidas de inventario ni necesidades anotadas entre esas fechas."
        />
    @else
        <x-tarjeta titulo="Insumos por pedir o reponer">
            <ul class="divide-y divide-gray-200">
                @foreach ($filas as $fila)
                    @php($descripcion = $lista ? $fila->descripcion : $fila['descripcion'])
                    @php($motivo = $lista ? $fila->motivo : $fila['motivo'])
                    @php($cantidad = $lista ? $fila->cantidad : $fila['cantidad'])
                    @php($enCatalogo = ($lista ? $fila->item_inventario_id : $fila['item_inventario_id']) !== null)
                    {{-- Sin flex-wrap: con una descripción larga, la cantidad se
                         caía a su propia línea y dejaba de alinearse con las demás. --}}
                    <li wire:key="fila-{{ $loop->index }}" class="flex items-start justify-between gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-gray-900">{{ $descripcion }}</p>
                            <p class="mt-0.5 text-sm text-gray-600">
                                {{ $motivo->etiqueta() }}
                                @unless ($enCatalogo)
                                    · <span class="text-amber-800">no está en el inventario</span>
                                @endunless
                            </p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold tabular-nums text-gray-900">{{ $cantidad }}</p>
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif

    {{-- Cerrar --}}
    @if ($periodoSinEmpezar && ! $lista)
        <p class="rounded-md bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-inset ring-sky-600/20" role="status">
            La última lista llegó hasta hoy, así que el periodo siguiente arranca mañana.
            Todavía no hay nada que cerrar.
        </p>
    @endif

    @if (! $lista && ! $periodoSinEmpezar)
        @can('cerrar', new \App\Models\ListaDeReposicion(['desde' => $desde, 'hasta' => $hasta]))
            <x-tarjeta titulo="Cerrar la lista del periodo">
                <p class="text-sm text-gray-600">
                    Al cerrarla, estas cifras se congelan y dejan de cambiar aunque el inventario
                    siga moviéndose. Es lo que se presenta como soporte de la solicitud de compra,
                    así que no se puede volver a abrir.
                </p>
                <div class="mt-3">
                    <label for="observaciones" class="mb-1 block text-sm font-medium text-gray-700">Observaciones para la carta</label>
                    <textarea wire:model="observaciones" id="observaciones" rows="2"
                              class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600"></textarea>
                </div>
                <div class="mt-3">
                    <x-boton type="button" wire:click="cerrar" wire:loading.attr="disabled">Cerrar la lista</x-boton>
                </div>
            </x-tarjeta>
        @endcan
    @endif

    {{-- Necesidades anotadas a mano --}}
    <x-tarjeta titulo="Lo que se pidió y no había">
        <p class="text-sm text-gray-600">
            Lo que el historial no sabe: algo que hizo falta y no está en el inventario, o de lo
            que hace falta más. Sigue pidiéndose en cada lista hasta que alguien la dé por
            atendida: si no llegó, en el semestre siguiente sigue haciendo falta.
        </p>

        @can('create', \App\Models\NecesidadDeReposicion::class)
            @if (! $anotando)
                <div class="mt-3">
                    <x-boton variante="secundario" type="button" wire:click="abrirFormulario">Anotar una necesidad</x-boton>
                </div>
            @else
                <div class="mt-3 space-y-3 rounded-md bg-gray-50 p-3 ring-1 ring-inset ring-gray-300">
                    <div>
                        <label for="itemId" class="mb-1 block text-sm font-medium text-gray-700">Ítem del inventario</label>
                        <select wire:model="itemId" id="itemId"
                                class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                            <option value="">No está en el inventario</option>
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}">{{ $item->nombre }}</option>
                            @endforeach
                        </select>
                        @error('itemId') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="descripcion" class="mb-1 block text-sm font-medium text-gray-700">Qué es</label>
                        <input type="text" wire:model="descripcion" id="descripcion"
                               placeholder="Pila CR2032 para el control del desfibrilador"
                               class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                        <p class="mt-1 text-xs text-gray-500">Obligatorio si no elegiste un ítem del inventario.</p>
                        @error('descripcion') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="cantidad" class="mb-1 block text-sm font-medium text-gray-700">Cuántas</label>
                        <input type="number" min="1" wire:model="cantidad" id="cantidad" inputmode="numeric"
                               class="w-28 rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                        @error('cantidad') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="justificacion" class="mb-1 block text-sm font-medium text-gray-700">Por qué hizo falta</label>
                        <textarea wire:model="justificacion" id="justificacion" rows="2"
                                  placeholder="Se pidió para la práctica de reanimación y no había."
                                  class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600"></textarea>
                        @error('justificacion') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-boton type="button" wire:click="anotarNecesidad" wire:loading.attr="disabled">Anotar</x-boton>
                        <x-boton variante="secundario" type="button" wire:click="cancelarFormulario">Cancelar</x-boton>
                    </div>
                </div>
            @endif
        @endcan

        @if ($necesidades->isNotEmpty())
            <ul class="mt-4 divide-y divide-gray-200">
                @foreach ($necesidades as $necesidad)
                    <li wire:key="necesidad-{{ $necesidad->id }}" class="py-3">
                        <p class="text-sm font-medium text-gray-900">
                            {{ $necesidad->cantidad }} × {{ $necesidad->queSePide() }}
                        </p>
                        <p class="mt-0.5 text-sm text-gray-600">{{ $necesidad->justificacion }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ $necesidad->registradaPor?->nombre ?? 'Sin registrar' }} ·
                            {{ $necesidad->fecha->format('d/m/Y') }}
                            @if ($necesidad->esDeFueraDelCatalogo())
                                · <span class="text-amber-800">no está en el inventario</span>
                            @endif
                        </p>

                        @if ($necesidad->listas_count > 0)
                            <p class="mt-0.5 text-xs text-rose-800">
                                {{ trans_choice(
                                    'Ya se pidió en :count carta anterior y no llegó|Ya se pidió en :count cartas anteriores y no llegó',
                                    $necesidad->listas_count,
                                    ['count' => $necesidad->listas_count],
                                ) }}
                            </p>
                        @endif

                        @can('atender', $necesidad)
                            @if ($atendiendo === $necesidad->id)
                                <div class="mt-2 space-y-2 rounded-md bg-gray-50 p-3 ring-1 ring-inset ring-gray-300">
                                    <label for="atencion-{{ $necesidad->id }}" class="block text-sm font-medium text-gray-700">
                                        Por qué deja de pedirse
                                    </label>
                                    <input type="text" wire:model="motivoAtencion" id="atencion-{{ $necesidad->id }}"
                                           placeholder="Llegó en la compra de diciembre."
                                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                                    @error('motivoAtencion') <p class="text-sm text-rose-700">{{ $message }}</p> @enderror

                                    {{-- Atender y mover inventario son dos actos: el enlace
                                         acompaña, pero no se encadenan (RF66). --}}
                                    <p class="text-xs text-gray-600">
                                        Dar por atendida no toca el inventario.
                                        @if ($necesidad->item_inventario_id !== null)
                                            <a href="{{ route('panel.inventario') }}" class="text-sky-800 underline">Reponer unidades</a>
                                            es otro registro, con su cantidad y su motivo.
                                        @else
                                            Si por fin se compró, hay que
                                            <a href="{{ route('panel.inventario.nuevo') }}" class="text-sky-800 underline">darlo de alta en el inventario</a>:
                                            todavía no está en el catálogo.
                                        @endif
                                    </p>

                                    <div class="flex flex-wrap gap-2">
                                        <x-boton type="button" wire:click="atender" wire:loading.attr="disabled" class="px-3 py-2">Confirmar</x-boton>
                                        <x-boton variante="secundario" type="button" wire:click="cancelarAtencion" class="px-3 py-2">Cancelar</x-boton>
                                    </div>
                                </div>
                            @else
                                <div class="mt-2">
                                    <x-boton variante="secundario" type="button" wire:click="pedirAtencion({{ $necesidad->id }})" class="px-3 py-2">
                                        Marcar como atendida
                                    </x-boton>
                                </div>
                            @endif
                        @endcan
                    </li>
                @endforeach
            </ul>
            <div class="mt-3">{{ $necesidades->links() }}</div>
        @endif
    </x-tarjeta>

    @if ($listasAnteriores->isNotEmpty())
        <x-tarjeta titulo="Listas cerradas">
            <ul class="divide-y divide-gray-200">
                @foreach ($listasAnteriores as $anterior)
                    <li wire:key="lista-{{ $anterior->id }}" class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900">
                                {{ $anterior->desde->format('d/m/Y') }} – {{ $anterior->hasta->format('d/m/Y') }}
                            </p>
                            <p class="text-sm text-gray-600">
                                @if ($anterior->estaCerrada())
                                    {{ $anterior->lineas_count }} líneas · cerrada por {{ $anterior->cerradaPor?->nombre ?? 'el laboratorio' }}
                                @else
                                    <span class="text-amber-800">En borrador</span>
                                @endif
                            </p>
                        </div>
                        @if ($anterior->estaCerrada())
                            <div class="flex shrink-0 gap-2">
                                <x-boton variante="secundario" href="{{ route('panel.reposicion.excel', $anterior) }}" class="px-3 py-2">Excel</x-boton>
                                <x-boton variante="secundario" href="{{ route('panel.reposicion.pdf', $anterior) }}" class="px-3 py-2">PDF</x-boton>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif
</div>
