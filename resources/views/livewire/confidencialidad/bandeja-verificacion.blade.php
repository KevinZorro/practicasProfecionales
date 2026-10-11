<div class="space-y-5">

    @if ($errorDeRegla)
        <x-aviso tono="peligro" role="alert">{{ $errorDeRegla }}</x-aviso>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-x-4 gap-y-3">
        <x-filtro-pastillas leyenda="Filtrar por estado" nombre="estado" wire:model.live="estado"
                            :opciones="['' => 'Todos'] + collect($estados)->mapWithKeys(fn ($unEstado) => [$unEstado->value => $unEstado->etiqueta()])->all()" />
        <div class="w-full sm:w-40">
            <label for="periodo" class="campo-etiqueta">Periodo</label>
            <input type="text" wire:model.live.debounce.400ms="periodo" id="periodo" placeholder="2026-2" class="campo">
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-[14px] text-gray-500">
            {{ trans_choice(':count formato|:count formatos', $entregas->total(), ['count' => $entregas->total()]) }}
        </p>
        <x-boton variante="texto" tamano="chico" href="{{ route('panel.formatos-confidencialidad.estado') }}">Ver quién lo tiene al día</x-boton>
    </div>

    @if ($entregas->isEmpty())
        <x-mensaje-vacio
            titulo="No hay nada por revisar"
            descripcion="Cuando alguien suba su formato aparecerá aquí."
        />
    @else
        <ul class="divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white">
            @foreach ($entregas as $entrega)
                <li wire:key="entrega-{{ $entrega->id }}" class="space-y-3 px-4 py-4 sm:px-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <x-iniciales :nombre="$entrega->firmante->nombre" class="mt-0.5 size-9 bg-gray-100 text-[13px] text-gray-700" />
                            <div class="min-w-0">
                                <p class="text-[15px] font-semibold leading-snug text-gray-900">{{ $entrega->firmante->nombre }}</p>
                                <p class="truncate text-[14px] text-gray-500">{{ $entrega->firmante->email }}</p>
                                <p class="text-[13px] text-gray-500">
                                    Periodo {{ $entrega->periodo_academico }} · subido el {{ $entrega->updated_at->format('d/m/Y H:i') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            {{-- Aquí también llegan formatos de docentes: sin esta
                                 etiqueta parece una cola solo de estudiantes. --}}
                            @if ($entrega->firmante->hasRole(\App\Enums\Rol::Docente->value))
                                <x-insignia tono="categoria">Docente</x-insignia>
                            @endif
                            <x-etiqueta-estado :estado="$entrega->estado" />
                        </div>
                    </div>

                    @if ($rechazando === $entrega->id)
                        <div class="space-y-3 rounded-xl bg-gray-50 p-3.5">
                            <div>
                                <label for="motivo-{{ $entrega->id }}" class="campo-etiqueta">
                                    Motivo de la devolución <span class="font-normal text-gray-500">(opcional)</span>
                                </label>
                                <textarea wire:model="motivoRechazo" id="motivo-{{ $entrega->id }}" rows="2" class="campo" x-init="$el.focus()"></textarea>
                                <p class="campo-ayuda">
                                    Quien lo entregó lo verá en su pantalla y podrá volver a subir el documento.
                                    El archivo devuelto se borra: son datos personales que ya no hacen falta.
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <x-boton variante="peligro" tamano="chico" type="button" wire:click="rechazar({{ $entrega->id }})">Devolver el formato</x-boton>
                                <x-boton variante="secundario" tamano="chico" type="button" wire:click="cancelar">Cancelar</x-boton>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-wrap gap-2 sm:pl-12">
                            @can('descargar', $entrega)
                                @if ($entrega->archivo_firmado_path)
                                    <x-boton variante="secundario" tamano="chico" href="{{ route('panel.formatos-confidencialidad.firmado', $entrega) }}">
                                        Ver documento
                                    </x-boton>
                                @endif
                            @endcan

                            @can('verificar', $entrega)
                                @if ($entrega->estado === \App\Enums\EstadoFormatoConfidencialidad::Cargado)
                                    <x-boton tamano="chico" type="button" wire:click="verificar({{ $entrega->id }})">Verificar</x-boton>
                                @endif
                            @endcan

                            @can('rechazar', $entrega)
                                @if ($entrega->estado === \App\Enums\EstadoFormatoConfidencialidad::Cargado)
                                    <x-boton variante="peligro-suave" tamano="chico" type="button" wire:click="pedirMotivo({{ $entrega->id }})">Devolver</x-boton>
                                @endif
                            @endcan
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <div>{{ $entregas->links() }}</div>
    @endif
</div>
