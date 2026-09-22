<div class="space-y-4">

    @php($estadoActual = $entrega?->estado ?? \App\Enums\EstadoFormatoConfidencialidad::Pendiente)

    {{-- Estado del periodo, bien visible: es lo primero que se busca al entrar. --}}
    <x-tarjeta>
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Periodo académico</p>
                <p class="text-lg font-semibold text-gray-900">{{ $periodo }}</p>
            </div>
            <x-etiqueta-estado :estado="$estadoActual" />
        </div>

        <p class="mt-3 text-sm text-gray-700">
            @switch($estadoActual)
                @case(\App\Enums\EstadoFormatoConfidencialidad::Verificado)
                    Tu formato de este periodo está verificado. No tienes nada pendiente.
                    @break
                @case(\App\Enums\EstadoFormatoConfidencialidad::Cargado)
                    Ya subiste tu documento. El laboratorio lo revisará; mientras tanto no tienes que hacer nada.
                    @break
                @default
                    Todavía no has entregado el formato de este periodo.
            @endswitch
        </p>

        {{--
            Por qué se lo piden otra vez: quien entregó el semestre pasado no
            entiende, si no se le dice, que esto se renueva cada semestre.
        --}}
        <x-slot:pie>
            El formato autoriza la grabación de las prácticas y <span class="font-medium">se renueva cada semestre</span>.
            El que hayas entregado en periodos anteriores sigue guardado, pero no vale para {{ $periodo }}.
        </x-slot:pie>
    </x-tarjeta>

    {{-- Motivo del rechazo --}}
    @if ($entrega?->motivo_rechazo && $estadoActual === \App\Enums\EstadoFormatoConfidencialidad::Pendiente)
        <div class="rounded-md bg-rose-50 px-4 py-3 ring-1 ring-inset ring-rose-600/20" role="alert">
            <p class="text-sm font-medium text-rose-900">Tu documento anterior fue devuelto</p>
            <p class="mt-1 text-sm text-rose-900">{{ $entrega->motivo_rechazo }}</p>
            <p class="mt-1 text-sm text-rose-900">Corrígelo y vuelve a subirlo aquí abajo.</p>
        </div>
    @endif

    {{-- Paso 1: descargar --}}
    <x-tarjeta titulo="1 · Descarga el documento">
        @if ($hayPlantilla)
            <p class="mb-3 text-sm text-gray-700">
                Descárgalo, léelo, fírmalo y vuelve aquí para subirlo.
            </p>
            <x-boton variante="secundario" href="{{ route('panel.formatos-confidencialidad.plantilla') }}" class="px-4 py-2.5">
                Descargar el formato
            </x-boton>
        @else
            <x-mensaje-vacio
                titulo="Todavía no hay documento disponible"
                descripcion="La administración del laboratorio aún no ha publicado el formato de este periodo. Vuelve más tarde."
            />
        @endif
    </x-tarjeta>

    {{-- Paso 2: subir --}}
    @if ($estadoActual !== \App\Enums\EstadoFormatoConfidencialidad::Verificado && $hayPlantilla)
        <x-tarjeta titulo="2 · Sube el documento firmado">
            @if ($errorDeRegla)
                <p class="mb-3 rounded-md bg-rose-50 px-3 py-2 text-sm text-rose-900 ring-1 ring-inset ring-rose-600/20" role="alert">
                    {{ $errorDeRegla }}
                </p>
            @endif

            <label for="documento" class="mb-1 block text-sm font-medium text-gray-700">Archivo firmado</label>
            <input type="file" wire:model="documento" id="documento" accept="application/pdf"
                   class="w-full rounded-md border border-gray-300 px-3 py-2.5 text-base file:mr-3 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm">

            <p class="mt-1 text-sm text-gray-600">
                Solo PDF, hasta {{ round((int) config('laboratorio.confidencialidad.tamano_maximo_kb') / 1024, 1) }} MB.
                Si lo escaneaste con el celular, únelo en un solo PDF antes de subirlo.
            </p>

            @error('documento') <p class="mt-1 text-sm text-rose-700">{{ $message }}</p> @enderror

            {{-- La subida de un escaneo desde el celular tarda: que se vea. --}}
            <div wire:loading wire:target="documento" class="mt-2 text-sm text-sky-800">
                Subiendo el archivo…
            </div>

            @if ($documento)
                <p class="mt-2 text-sm text-gray-700">Listo para enviar: {{ $documento->getClientOriginalName() }}</p>
            @endif

            <x-slot:pie>
                <x-boton wire:click="entregar" wire:loading.attr="disabled" wire:target="entregar,documento" class="px-4 py-2.5">
                    <span wire:loading.remove wire:target="entregar">Enviar para verificación</span>
                    <span wire:loading wire:target="entregar">Enviando…</span>
                </x-boton>
            </x-slot:pie>
        </x-tarjeta>
    @endif

    {{-- Documento ya entregado --}}
    @if ($entrega?->archivo_firmado_path)
        <x-tarjeta titulo="Tu documento">
            <p class="mb-3 text-sm text-gray-700">
                Subido el {{ $entrega->updated_at->format('d/m/Y H:i') }}.
            </p>
            <x-boton variante="secundario" href="{{ route('panel.formatos-confidencialidad.firmado', $entrega) }}" class="px-4 py-2.5">
                Descargar lo que subí
            </x-boton>
        </x-tarjeta>
    @endif
</div>
