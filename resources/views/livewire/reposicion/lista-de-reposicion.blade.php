<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">
            {{ $errorDeRegla }}
        </x-aviso>
    @endif

    <x-tarjeta>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label for="desde" class="campo-etiqueta">Desde</label>
                @if ($origenFijado === null)
                    <input type="date" wire:model.live="desde" id="desde" @disabled($lista !== null)
                           class="campo">
                    <p class="campo-ayuda">La primera lista elige desde cuándo cuenta. Después arranca sola.</p>
                @else
                    <p class="campo bg-gray-50 text-gray-700">
                        {{ \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') }}
                    </p>
                    <p class="campo-ayuda">Donde terminó la última lista cerrada: así ningún movimiento queda fuera ni por duplicado.</p>
                @endif
            </div>
            <div>
                <label for="hasta" class="campo-etiqueta">Hasta</label>
                <input type="date" wire:model.live="hasta" id="hasta" @disabled($lista !== null)
                       class="campo">
            </div>
        </div>

        @if ($lista)
            <p class="mt-3 text-sm text-gray-700">
                Lista cerrada por {{ $lista->cerradaPor?->nombre ?? 'el laboratorio' }}
                el {{ $lista->cerrada_at->format('d/m/Y H:i') }}. Sus cifras ya no cambian.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-boton tamano="chico" href="{{ route('panel.reposicion.excel', $lista) }}">Descargar Excel</x-boton>
                <x-boton variante="secundario" tamano="chico" href="{{ route('panel.reposicion.pdf', $lista) }}">Descargar PDF</x-boton>
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
            <ul class="divide-y divide-gray-100">
                @foreach ($filas as $fila)
                    @php($descripcion = $lista ? $fila->descripcion : $fila['descripcion'])
                    @php($motivo = $lista ? $fila->motivo : $fila['motivo'])
                    @php($cantidad = $lista ? $fila->cantidad : $fila['cantidad'])
                    @php($enCatalogo = ($lista ? $fila->item_inventario_id : $fila['item_inventario_id']) !== null)
                    {{-- Sin flex-wrap: con una descripción larga, la cantidad se
                         caía a su propia línea y dejaba de alinearse con las demás. --}}
                    <li wire:key="fila-{{ $loop->index }}" class="flex items-start justify-between gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-[15px] font-semibold text-gray-900">{{ $descripcion }}</p>
                            <p class="mt-0.5 text-sm text-gray-600">
                                {{ $motivo->etiqueta() }}
                                @unless ($enCatalogo)
                                    · <span class="text-amber-800">no está en el inventario</span>
                                @endunless
                            </p>
                        </div>
                        <p class="shrink-0 text-sm font-semibold text-gray-900">{{ $cantidad }}</p>
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif

    {{-- Cerrar --}}
    @if ($periodoSinEmpezar && ! $lista)
        <x-aviso tono="info" role="status">
            La última lista llegó hasta hoy, así que el periodo siguiente arranca mañana.
            Todavía no hay nada que cerrar.
        </x-aviso>
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
                    <label for="observaciones" class="campo-etiqueta">Observaciones para la carta</label>
                    <textarea wire:model="observaciones" id="observaciones" rows="2"
                              class="campo"></textarea>
                </div>
                <div class="mt-3">
                    <x-boton variante="peligro" type="button" wire:click="cerrar" wire:loading.attr="disabled" wire:target="cerrar"
                             wire:confirm="¿Cerrar la lista? Sus cifras quedan congeladas y no se puede volver a abrir.">Cerrar la lista</x-boton>
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
                <div class="mt-4 space-y-4 rounded-xl bg-gray-50 p-3.5">
                    <div>
                        <label for="itemId" class="campo-etiqueta">Ítem del inventario</label>
                        <select wire:model="itemId" id="itemId"
                                class="campo">
                            <option value="">No está en el inventario</option>
                            @foreach ($items as $item)
                                <option value="{{ $item->id }}">{{ $item->nombre }}</option>
                            @endforeach
                        </select>
                        @error('itemId') <p class="campo-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="descripcion" class="campo-etiqueta">Qué es</label>
                        <input type="text" wire:model="descripcion" id="descripcion"
                               placeholder="Pila CR2032 para el control del desfibrilador"
                               class="campo">
                        <p class="campo-ayuda">Obligatorio si no elegiste un ítem del inventario.</p>
                        @error('descripcion') <p class="campo-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="cantidad" class="campo-etiqueta">Cuántas</label>
                        <input type="number" min="1" wire:model="cantidad" id="cantidad" inputmode="numeric" class="campo w-28">
                        @error('cantidad') <p class="campo-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="justificacion" class="campo-etiqueta">Por qué hizo falta</label>
                        <textarea wire:model="justificacion" id="justificacion" rows="2"
                                  placeholder="Se pidió para la práctica de reanimación y no había."
                                  class="campo"></textarea>
                        @error('justificacion') <p class="campo-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <x-boton type="button" wire:click="anotarNecesidad" wire:loading.attr="disabled">Anotar</x-boton>
                        <x-boton variante="secundario" type="button" wire:click="cancelarFormulario">Cancelar</x-boton>
                    </div>
                </div>
            @endif
        @endcan

        @if ($necesidades->isNotEmpty())
            <ul class="mt-4 divide-y divide-gray-100">
                @foreach ($necesidades as $necesidad)
                    <li wire:key="necesidad-{{ $necesidad->id }}" class="py-3">
                        <p class="text-[15px] font-semibold text-gray-900">
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
                            <p class="mt-0.5 text-xs text-rojo-800">
                                {{ trans_choice(
                                    'Ya se pidió en :count carta anterior y no llegó|Ya se pidió en :count cartas anteriores y no llegó',
                                    $necesidad->listas_count,
                                    ['count' => $necesidad->listas_count],
                                ) }}
                            </p>
                        @endif

                        @can('atender', $necesidad)
                            @if ($atendiendo === $necesidad->id)
                                <div class="mt-3 space-y-3 rounded-xl bg-gray-50 p-3.5">
                                    <label for="atencion-{{ $necesidad->id }}" class="campo-etiqueta">Por qué deja de pedirse</label>
                                    <input type="text" wire:model="motivoAtencion" id="atencion-{{ $necesidad->id }}"
                                           placeholder="Llegó en la compra de diciembre."
                                           class="campo">
                                    @error('motivoAtencion') <p class="campo-error">{{ $message }}</p> @enderror

                                    {{-- Atender y mover inventario son dos actos: el enlace
                                         acompaña, pero no se encadenan (RF66). --}}
                                    <p class="text-[13px] leading-snug text-gray-600">
                                        Dar por atendida no toca el inventario.
                                        @if ($necesidad->item_inventario_id !== null)
                                            <a href="{{ route('panel.inventario') }}" class="font-semibold text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900">Reponer unidades</a>
                                            es otro registro, con su cantidad y su motivo.
                                        @else
                                            Si por fin se compró, hay que
                                            <a href="{{ route('panel.inventario.nuevo') }}" class="font-semibold text-gray-900 underline decoration-gray-300 underline-offset-4 hover:decoration-gray-900">darlo de alta en el inventario</a>:
                                            todavía no está en el catálogo.
                                        @endif
                                    </p>

                                    <div class="flex flex-wrap gap-2">
                                        <x-boton type="button" wire:click="atender" wire:loading.attr="disabled" tamano="chico">Confirmar</x-boton>
                                        <x-boton variante="secundario" type="button" wire:click="cancelarAtencion" tamano="chico">Cancelar</x-boton>
                                    </div>
                                </div>
                            @else
                                <div class="mt-2">
                                    <x-boton variante="secundario" tamano="chico" type="button" wire:click="pedirAtencion({{ $necesidad->id }})">
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
            <ul class="divide-y divide-gray-100">
                @foreach ($listasAnteriores as $anterior)
                    <li wire:key="lista-{{ $anterior->id }}" class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <div class="min-w-0">
                            <p class="text-[15px] font-semibold text-gray-900">
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
                                <x-boton variante="secundario" href="{{ route('panel.reposicion.excel', $anterior) }}" tamano="chico">Excel</x-boton>
                                <x-boton variante="secundario" href="{{ route('panel.reposicion.pdf', $anterior) }}" tamano="chico">PDF</x-boton>
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-tarjeta>
    @endif
</div>
