<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">
            {{ $errorDeRegla }}
        </x-aviso>
    @endif

    <x-tarjeta>
        @if ($laSesion)
            <x-aviso class="mb-4">
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                    <span>
                        Solo la sesión de {{ $laSesion->casoClinico->nombre }} del {{ $laSesion->fecha->format('d/m/Y') }}
                        {{ $laSesion->grupo ? '· grupo '.$laSesion->grupo : '' }}: sus estudiantes y su docente.
                    </span>
                    <button type="button" wire:click="$set('sesion', null)" class="font-semibold text-gray-900 underline decoration-gray-400 underline-offset-4 hover:decoration-gray-900">Ver todos</button>
                </div>
            </x-aviso>
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-1">
                <label for="buscar" class="campo-etiqueta">Buscar</label>
                <input type="search" wire:model.live.debounce.400ms="busqueda" id="buscar" placeholder="Nombre, correo o código"
                       class="campo">
            </div>
            <div>
                <label for="periodo" class="campo-etiqueta">Periodo</label>
                <input type="text" wire:model.live.debounce.400ms="periodo" id="periodo"
                       class="campo">
            </div>
            <div>
                <label for="situacion" class="campo-etiqueta">Situación</label>
                <select wire:model.live="situacion" id="situacion"
                        class="campo">
                    <option value="">Todos</option>
                    <option value="{{ \App\Livewire\Confidencialidad\EstadoDeFirmantes::LE_FALTA }}">Les falta</option>
                    <option value="{{ \App\Livewire\Confidencialidad\EstadoDeFirmantes::AL_DIA }}">Al día</option>
                </select>
            </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="materia" class="campo-etiqueta">Materia</label>
                <select wire:model.live="materia" id="materia"
                        class="campo">
                    <option value="">Todas</option>
                    @foreach ($materias as $unaMateria)
                        <option value="{{ $unaMateria->id }}">{{ $unaMateria->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="programa" class="campo-etiqueta">Programa</label>
                <select wire:model.live="programa" id="programa"
                        class="campo">
                    <option value="">Todos</option>
                    @foreach ($programas as $unPrograma)
                        <option value="{{ $unPrograma }}">{{ $unPrograma }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
            <p class="text-[14px] text-gray-500">
                {{ trans_choice(':count persona|:count personas', $firmantes->total(), ['count' => $firmantes->total()]) }}
                en {{ $periodo }}
            </p>
            <x-boton variante="texto" tamano="chico" type="button" wire:click="limpiarFiltros">Limpiar filtros</x-boton>
        </div>
    </x-tarjeta>

    @if ($firmantes->isEmpty())
        <x-mensaje-vacio titulo="No hay nadie que coincida" descripcion="Prueba a quitar algún filtro." />
    @else
        <ul class="divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white">
            @foreach ($firmantes as $firmante)
                @php($entrega = $firmante->formatosDeConfidencialidad->first())
                <li wire:key="firmante-{{ $firmante->id }}" class="px-4 py-4 sm:px-5">
                        {{-- Columna y no fila: a 390 px el nombre y las etiquetas
                             no caben juntos y el nombre se corta. --}}
                        <div class="flex flex-col gap-2">
                            <div class="min-w-0">
                                <p class="text-[15px] font-semibold leading-snug text-gray-900">{{ $firmante->nombre }}</p>
                                <p class="truncate text-[14px] text-gray-500">
                                    {{ $firmante->email }}@if ($firmante->codigo_institucional) · código {{ $firmante->codigo_institucional }}@endif
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                {{-- El docente firma el mismo formato, pero quien revisa
                                     necesita distinguirlo de un vistazo (RF51-RF52). --}}
                                @if ($firmante->hasRole(\App\Enums\Rol::Docente->value))
                                    <x-insignia>Docente</x-insignia>
                                @endif

                                {{-- La entrega en físico no es un estado del documento: convive
                                     con él, así que se pinta como etiqueta aparte (RF53). --}}
                                @if ($entrega?->entregadoEnFisico())
                                    <x-insignia tono="atencion">Entregado en físico</x-insignia>
                                @endif

                                @if ($entrega)
                                    <x-etiqueta-estado :estado="$entrega->estado" />
                                @else
                                    <x-insignia tono="neutro">Sin entregar</x-insignia>
                                @endif
                            </div>
                        </div>

                        @if ($entrega?->entregadoEnFisico())
                            <p class="mt-2 text-[14px] text-gray-600">
                                Recibido por {{ $entrega->recibidoFisicoPor?->nombre ?? 'el laboratorio' }}
                                el {{ $entrega->recibido_fisico_at->format('d/m/Y') }}.
                                @if ($entrega->estado !== \App\Enums\EstadoFormatoConfidencialidad::Verificado)
                                    Puede ingresar; falta que suba el escaneo.
                                @endif
                            </p>
                        @elseif ($entrega?->estado !== \App\Enums\EstadoFormatoConfidencialidad::Verificado)
                            <p class="mt-2 text-[14px] font-medium text-amber-800">No tiene el formato al día para {{ $periodo }}.</p>

                            @can('marcarEntregaFisica', \App\Models\FormatoConfidencialidad::class)
                                <div class="mt-3">
                                    <x-boton variante="secundario" tamano="chico" type="button"
                                             wire:click="marcarEntregaFisica({{ $firmante->id }})"
                                             wire:loading.attr="disabled">
                                        Recibí el formato en físico
                                    </x-boton>
                                </div>
                            @endcan
                        @endif
                </li>
            @endforeach
        </ul>

        <div>{{ $firmantes->links() }}</div>
    @endif
</div>
