{{--
    Lo que tiene más de dos semestres (retención de datos, decisión del
    2026-11-08). Se conservan el periodo vigente y el anterior; de un formato
    firmado se borra el PDF y se queda la fila.
--}}
<div class="space-y-5">
    <x-tarjeta titulo="Lo que se conserva">
        @if ($pendiente->corte === null)
            <p class="text-[15px] text-gray-700">
                Todavía hay un solo periodo académico: se conserva todo. Cuando se abra el
                tercero, lo del primero se podrá depurar.
            </p>
        @else
            <p class="text-[15px] text-gray-700">
                Los periodos {{ implode(' y ', $pendiente->periodosConservados) }}: todo lo que
                empezó desde el {{ $pendiente->corte->format('d/m/Y') }}.
            </p>
        @endif
    </x-tarjeta>

    @if ($pendiente->hayAlgo())
        <x-tarjeta titulo="Datos de más de dos semestres">
            <ul class="space-y-1.5 text-[15px] text-gray-700">
                <li>{{ trans_choice(':count evaluación, con sus estudiantes y su checklist|:count evaluaciones, con sus estudiantes y su checklist', $pendiente->evaluaciones, ['count' => $pendiente->evaluaciones]) }}</li>
                <li>{{ trans_choice(':count entrada de la bitácora|:count entradas de la bitácora', $pendiente->entradasDeBitacora, ['count' => $pendiente->entradasDeBitacora]) }}</li>
                <li>
                    {{ trans_choice('El documento de :count formato firmado|Los documentos de :count formatos firmados', $pendiente->formatos, ['count' => $pendiente->formatos]) }}
                    @if ($pendiente->periodosDeLosFormatos !== [])
                        (periodo {{ implode(', ', $pendiente->periodosDeLosFormatos) }}). La fila, con quién firmó y quién verificó, se queda.
                    @endif
                </li>
            </ul>

            @if ($pendiente->formatos > 0)
                <div class="mt-4 space-y-3">
                    <x-boton variante="secundario" tamano="chico" :href="route('panel.depuracion.formatos')">
                        Descargar los formatos en un ZIP
                    </x-boton>
                    <label class="flex items-start gap-2 text-[14px] text-gray-700">
                        <input type="checkbox" wire:model="descargoLosFormatos" class="mt-0.5 rounded border border-gray-300">
                        Ya descargué los formatos y los guardé donde el laboratorio los conserva.
                    </label>
                    @error('descargoLosFormatos') <p class="campo-error">{{ $message }}</p> @enderror
                </div>
            @endif

            @if ($errorDeRegla)
                <x-aviso tono="peligro" role="alert" class="mt-4">{{ $errorDeRegla }}</x-aviso>
            @endif

            <x-slot:pie>
                <x-aviso tono="peligro">Depurar no se deshace: lo borrado no vuelve.</x-aviso>
                <x-boton variante="peligro" type="button" wire:click="depurar" wire:loading.attr="disabled" class="mt-3">
                    Depurar lo de más de dos semestres
                </x-boton>
            </x-slot:pie>
        </x-tarjeta>
    @else
        <x-mensaje-vacio titulo="Nada que depurar" descripcion="Todo lo que hay tiene menos de dos semestres." />
    @endif
</div>
