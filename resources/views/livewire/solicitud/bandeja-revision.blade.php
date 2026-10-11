<div class="space-y-5">

    <x-filtro-pastillas leyenda="Filtrar por estado" nombre="estado" wire:model.live="estado"
                        :opciones="['' => 'Todas'] + collect($estados)->mapWithKeys(fn ($unEstado) => [$unEstado->value => $unEstado->etiqueta()])->all()" />

    @if ($sinCobertura !== [])
        {{-- RF66: el inventario dejó de dar para prácticas ya aprobadas.
             Solo se avisa: qué hacer con ellas lo decide el laboratorio. --}}
        <x-aviso tono="peligro" role="alert">
            {{ trans_choice(
                'Hay :count práctica aprobada sin unidades suficientes|Hay :count prácticas aprobadas sin unidades suficientes',
                count($sinCobertura),
                ['count' => count($sinCobertura)],
            ) }}:
            alguno de sus equipos dejó de estar operativo después de aprobarlas. Están marcadas abajo.
        </x-aviso>
    @endif

    @if ($solicitudes->isEmpty())
        <x-mensaje-vacio titulo="No hay solicitudes" descripcion="Cuando un docente pida un escenario aparecerá aquí, ordenada por la fecha de la práctica." />
    @else
        <ul class="divide-y divide-gray-100 overflow-hidden rounded-2xl bg-white" wire:loading.class="opacity-60" wire:target="estado, gotoPage, nextPage, previousPage">
            @foreach ($solicitudes as $solicitud)
                <x-fila-de-solicitud :solicitud="$solicitud" wire:key="solicitud-{{ $solicitud->id }}">
                    @if (in_array($solicitud->id, $sinCobertura, true))
                        <p class="font-medium text-rojo-800">
                            Sin unidades suficientes: alguno de los equipos de esta práctica dejó de
                            estar operativo después de aprobarla.
                        </p>
                    @endif
                </x-fila-de-solicitud>
            @endforeach
        </ul>

        <div>{{ $solicitudes->links() }}</div>
    @endif
</div>
