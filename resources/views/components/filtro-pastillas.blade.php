{{--
    Filtro de pocas opciones en pastillas: todas a la vista, a un toque, en
    lugar de un desplegable que esconde lo que hay. Son radios de verdad, así
    que el teclado y el lector de pantalla los tratan como un grupo.

    Uso: <x-filtro-pastillas leyenda="Estado" nombre="estado" wire:model.live="estado" :opciones="['' => 'Todas', ...]" />
--}}
@props(['opciones', 'leyenda', 'nombre'])

<fieldset {{ $attributes->whereDoesntStartWith('wire:model')->class('min-w-0') }}>
    <legend class="sr-only">{{ $leyenda }}</legend>
    <div class="flex flex-wrap gap-1.5">
        @foreach ($opciones as $valor => $etiqueta)
            <label class="cursor-pointer">
                <input type="radio" name="{{ $nombre }}" value="{{ $valor }}" {{ $attributes->whereStartsWith('wire:model') }} class="peer sr-only">
                <span class="inline-flex h-9 select-none items-center rounded-full bg-white px-3.5 text-[14px] font-medium text-gray-700 ring-1 ring-inset ring-gray-300 transition-colors duration-150 hover:text-gray-900 hover:ring-gray-400 peer-checked:bg-gray-900 peer-checked:text-white peer-checked:ring-gray-900 peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-rojo">
                    {{ $etiqueta }}
                </span>
            </label>
        @endforeach
    </div>
</fieldset>
