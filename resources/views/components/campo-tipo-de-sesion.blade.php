{{--
    Práctica o evaluación, elegida a propósito: no viene marcada ninguna.
    Con la práctica puesta de antemano, quien pedía una evaluación y no lo
    notaba se quedaba sin poder evaluar (CLAUDE.md, regla 1). Cada opción
    dice qué cambia en la sesión.

    Son radios de verdad, en un fieldset con su leyenda: el teclado y el
    lector de pantalla los tratan como un grupo.

    Uso: <x-campo-tipo-de-sesion wire:model="tipo" />
--}}
<fieldset {{ $attributes->whereDoesntStartWith('wire:model')->class('min-w-0') }}>
    <legend class="campo-etiqueta">Tipo de sesión</legend>
    <div class="grid gap-2 sm:grid-cols-2">
        @foreach (\App\Enums\TipoSesion::cases() as $tipo)
            <label class="flex cursor-pointer items-start gap-3 rounded-[10px] bg-white px-3.5 py-3 ring-1 ring-inset ring-gray-300 transition-colors duration-150 hover:ring-gray-400 has-[:checked]:ring-2 has-[:checked]:ring-gray-900 has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-rojo">
                <input type="radio" name="tipo" value="{{ $tipo->value }}" {{ $attributes->whereStartsWith('wire:model') }}
                       class="mt-0.5 size-[18px] shrink-0 accent-gray-900 focus:outline-none">
                <span class="min-w-0">
                    <span class="block text-[15px] font-semibold text-gray-900">{{ $tipo->etiqueta() }}</span>
                    <span class="mt-0.5 block text-[13px] leading-snug text-gray-600">{{ $tipo->queSignifica() }}</span>
                </span>
            </label>
        @endforeach
    </div>
    @error('tipo') <p class="campo-error">{{ $message }}</p> @enderror
</fieldset>
