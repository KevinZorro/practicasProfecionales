{{--
    Campo de contraseña de las pantallas de acceso, con «Mostrar» para
    revisar lo escrito: en el celular es la forma de no equivocarse con una
    frase larga. El botón nace oculto y lo enciende el script de
    pantalla-de-acceso: sin JavaScript queda un campo normal.
--}}
@props(['id', 'autocomplete', 'describedby' => null])

<div class="relative">
    <input type="password" id="{{ $id }}" name="{{ $id }}" autocomplete="{{ $autocomplete }}"
           @if ($describedby) aria-describedby="{{ $describedby }}" @endif
           {{ $attributes->class(['campo rounded-xl py-3 pr-24 sm:py-3 sm:text-base']) }}>
    <button type="button" hidden data-mostrar-contrasena="{{ $id }}" aria-controls="{{ $id }}" aria-pressed="false"
            class="absolute inset-y-1 right-1 rounded-lg px-3 text-[14px] font-semibold text-portada-gris transition-colors duration-150 hover:text-portada-tinta focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-0 focus-visible:outline-portada-rojo">Mostrar</button>
</div>
