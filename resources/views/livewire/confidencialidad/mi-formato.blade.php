<div class="space-y-5">

    @php($estadoActual = $entrega?->estado ?? \App\Enums\EstadoFormatoConfidencialidad::Pendiente)

    {{-- Estado del periodo, bien visible: es lo primero que se busca al entrar. --}}
    <x-tarjeta>
        <div class="flex flex-wrap items-start justify-between gap-2">
            <dl class="min-w-0">
                <x-dato etiqueta="Periodo académico">
                    <span class="text-[1.375rem] font-bold leading-tight tracking-[-0.02em]">{{ $periodo ?? 'Sin periodo abierto' }}</span>
                </x-dato>
            </dl>
            <x-etiqueta-estado :estado="$estadoActual" />
        </div>

        <p class="mt-3 text-[15px] leading-snug text-gray-700">
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
            <p>
                El formato autoriza la grabación de las prácticas y <span class="font-semibold text-gray-800">se renueva cada periodo académico</span>.
                @if ($periodo)
                    El que hayas entregado en periodos anteriores sigue guardado, pero no vale para {{ $periodo }}.
                @endif
            </p>
        </x-slot:pie>
    </x-tarjeta>

    {{-- Motivo del rechazo --}}
    @if ($entrega?->motivo_rechazo && $estadoActual === \App\Enums\EstadoFormatoConfidencialidad::Pendiente)
        <x-aviso tono="peligro" role="alert">
            <p class="font-semibold">Tu documento anterior fue devuelto</p>
            <p class="mt-0.5">{{ $entrega->motivo_rechazo }}</p>
            <p class="mt-0.5">Corrígelo y vuelve a subirlo aquí abajo.</p>
        </x-aviso>
    @endif

    {{-- Paso 1: descargar --}}
    <x-tarjeta titulo="1 · Descarga el documento">
        @if ($hayPlantilla)
            <p class="mb-4 text-[15px] leading-snug text-gray-700">
                Descárgalo, léelo, fírmalo y vuelve aquí para subirlo.
            </p>
            <x-boton variante="secundario" href="{{ route('panel.formatos-confidencialidad.plantilla') }}">
                Descargar el formato
            </x-boton>
        @else
            <x-mensaje-vacio
                titulo="Todavía no hay documento disponible"
                descripcion="La administración del laboratorio aún no ha publicado el formato de este periodo. Vuelve más tarde."
            />
        @endif
    </x-tarjeta>

    {{-- Entre semestres no se recibe nada: no se sabría a qué periodo pertenece. --}}
    @if ($estadoActual !== \App\Enums\EstadoFormatoConfidencialidad::Verificado && $hayPlantilla && ! $recibeEntregas)
        <x-mensaje-vacio
            titulo="El laboratorio todavía no ha abierto el periodo"
            descripcion="Podrás subir tu formato en cuanto el laboratorio abra el periodo académico."
        />
    @endif

    {{-- Paso 2: subir --}}
    @if ($estadoActual !== \App\Enums\EstadoFormatoConfidencialidad::Verificado && $hayPlantilla && $recibeEntregas)
        <x-tarjeta titulo="2 · Sube el documento firmado">
            @if ($errorDeRegla)
                <x-aviso tono="peligro" role="alert">
                    {{ $errorDeRegla }}
                </x-aviso>
            @endif

            <label for="documento" class="campo-etiqueta">Archivo firmado</label>
            <input type="file" wire:model="documento" id="documento" accept="application/pdf" class="campo-archivo">

            <p class="campo-ayuda">
                Solo PDF, hasta {{ round((int) config('laboratorio.confidencialidad.tamano_maximo_kb') / 1024, 1) }} MB.
                Si lo escaneaste con el celular, únelo en un solo PDF antes de subirlo.
            </p>

            @error('documento') <p class="campo-error">{{ $message }}</p> @enderror

            {{-- La subida de un escaneo desde el celular tarda: que se vea. --}}
            <p wire:loading wire:target="documento" class="mt-2 text-[14px] font-medium text-gray-700">
                Subiendo el archivo…
            </p>

            @if ($documento)
                <x-aviso tono="exito" class="mt-3">Listo para enviar: {{ $documento->getClientOriginalName() }}</x-aviso>
            @endif

            <x-slot:pie>
                <x-boton type="button" wire:click="entregar" wire:loading.attr="disabled" wire:target="entregar,documento">
                    <span wire:loading.remove wire:target="entregar">Enviar para verificación</span>
                    <span wire:loading wire:target="entregar">Enviando…</span>
                </x-boton>
            </x-slot:pie>
        </x-tarjeta>
    @endif

    {{-- Documento ya entregado --}}
    @if ($entrega?->archivo_firmado_path)
        <x-tarjeta titulo="Tu documento">
            <p class="mb-4 text-[15px] leading-snug text-gray-700">
                Subido el {{ $entrega->updated_at->format('d/m/Y H:i') }}.
            </p>
            <x-boton variante="secundario" href="{{ route('panel.formatos-confidencialidad.firmado', $entrega) }}">
                Descargar lo que subí
            </x-boton>
        </x-tarjeta>
    @endif
</div>
