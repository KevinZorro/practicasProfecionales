<div class="space-y-4">

    @if ($errorDeRegla)
        <p class="rounded-md bg-rose-50 px-4 py-3 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
            {{ $errorDeRegla }}
        </p>
    @endif

    <x-tarjeta>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label for="estado" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Estado</label>
                <select wire:model.live="estado" id="estado"
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
                    <option value="">Todos</option>
                    @foreach ($estados as $unEstado)
                        <option value="{{ $unEstado->value }}">{{ $unEstado->etiqueta() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="periodo" class="mb-1 block text-xs font-medium uppercase tracking-wide text-gray-500">Periodo</label>
                <input type="text" wire:model.live.debounce.400ms="periodo" id="periodo" placeholder="2026-2"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-sky-600 focus:ring-sky-600">
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600">
                {{ trans_choice(':count formato|:count formatos', $entregas->total(), ['count' => $entregas->total()]) }}
            </p>
            <x-boton variante="secundario" href="{{ route('panel.formatos-confidencialidad.estado') }}">Ver quién lo tiene al día</x-boton>
        </div>
    </x-tarjeta>

    @if ($entregas->isEmpty())
        <x-mensaje-vacio
            titulo="No hay nada por revisar"
            descripcion="Cuando alguien suba su formato aparecerá aquí."
        />
    @else
        <ul class="space-y-3">
            @foreach ($entregas as $entrega)
                <li wire:key="entrega-{{ $entrega->id }}">
                    <x-tarjeta>
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $entrega->firmante->nombre }}</p>
                                <p class="truncate text-sm text-gray-600">{{ $entrega->firmante->email }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                {{-- Aquí también llegan formatos de docentes: sin esta
                                     etiqueta parece una cola solo de estudiantes. --}}
                                @if ($entrega->firmante->hasRole(\App\Enums\Rol::Docente->value))
                                    <span class="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-medium text-violet-800 ring-1 ring-inset ring-violet-600/20">
                                        Docente
                                    </span>
                                @endif
                                <x-etiqueta-estado :estado="$entrega->estado" />
                            </div>
                        </div>

                        <dl class="mt-3 grid grid-cols-2 gap-3">
                            <x-dato etiqueta="Periodo">{{ $entrega->periodo_academico }}</x-dato>
                            <x-dato etiqueta="Subido">{{ $entrega->updated_at->format('d/m/Y H:i') }}</x-dato>
                        </dl>

                        <div class="mt-3 flex flex-wrap gap-2 border-t border-gray-200 pt-3">
                            @can('descargar', $entrega)
                                @if ($entrega->archivo_firmado_path)
                                    <x-boton variante="secundario" href="{{ route('panel.formatos-confidencialidad.firmado', $entrega) }}" class="px-3 py-2">
                                        Ver documento
                                    </x-boton>
                                @endif
                            @endcan

                            @can('verificar', $entrega)
                                @if ($entrega->estado === \App\Enums\EstadoFormatoConfidencialidad::Cargado)
                                    <x-boton type="button" wire:click="verificar({{ $entrega->id }})" class="px-3 py-2">Verificar</x-boton>
                                @endif
                            @endcan

                            @can('rechazar', $entrega)
                                @if ($entrega->estado === \App\Enums\EstadoFormatoConfidencialidad::Cargado)
                                    <x-boton variante="secundario" type="button" wire:click="pedirMotivo({{ $entrega->id }})" class="px-3 py-2">
                                        Devolver
                                    </x-boton>
                                @endif
                            @endcan
                        </div>

                        @if ($rechazando === $entrega->id)
                            <div class="mt-3 space-y-2 rounded-md bg-rose-50 p-3 ring-1 ring-inset ring-rose-600/20">
                                <label for="motivo-{{ $entrega->id }}" class="block text-sm font-medium text-rose-900">
                                    Motivo <span class="font-normal">(opcional)</span>
                                </label>
                                <textarea wire:model="motivoRechazo" id="motivo-{{ $entrega->id }}" rows="2"
                                          class="w-full rounded-md border border-gray-300 px-3 py-2 text-base focus:border-rose-600 focus:ring-rose-600"></textarea>
                                <p class="text-xs text-rose-900">
                                    Quien lo entregó lo verá en su pantalla y podrá volver a subir el documento.
                                    El archivo devuelto se borra: son datos personales que ya no hacen falta.
                                </p>
                                <div class="flex flex-wrap gap-2">
                                    <x-boton type="button" wire:click="rechazar({{ $entrega->id }})" class="px-4 py-2">Confirmar</x-boton>
                                    <x-boton variante="secundario" type="button" wire:click="cancelar" class="px-4 py-2">Cancelar</x-boton>
                                </div>
                            </div>
                        @endif
                    </x-tarjeta>
                </li>
            @endforeach
        </ul>

        <div>{{ $entregas->links() }}</div>
    @endif
</div>
