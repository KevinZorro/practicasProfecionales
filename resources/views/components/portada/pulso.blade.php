{{--
    La línea de pulso, firma visual de la portada. Tres trazos en fila: la
    recta de la izquierda, el latido y la recta de la derecha. El latido
    tiene ancho fijo para no deformarse en ninguna pantalla; las rectas se
    estiran con la ventana (preserveAspectRatio="none" sobre una horizontal
    no cambia su grosor). Los tres comparten alto y línea base, así que
    empalman sin costura.

    Con pathLength="1" el CSS dibuja el del hero una sola vez con
    stroke-dashoffset (animación 1 de DESIGN.md); los demás son estáticos.
    Sin animación se ven completos.
--}}
@props(['animado' => false])

<div {{ $attributes->class(['portada-pulso flex items-center text-portada-rojo', 'portada-pulso--animado' => $animado]) }} aria-hidden="true">
    <svg class="h-20 min-w-0 flex-1" viewBox="0 0 100 80" preserveAspectRatio="none" fill="none">
        <path d="M0 60H100" pathLength="1" stroke="currentColor" stroke-width="2"/>
    </svg>
    <svg class="h-20 w-[168px] shrink-0" viewBox="0 0 168 80" fill="none">
        <path d="M0 60H20C26 60 28 52 34 52S42 60 48 60H64L70 66L80 6L91 74L98 60H112C120 60 124 46 134 46S148 60 156 60H168"
              pathLength="1" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
    </svg>
    <svg class="h-20 min-w-0 flex-1" viewBox="0 0 100 80" preserveAspectRatio="none" fill="none">
        <path d="M0 60H100" pathLength="1" stroke="currentColor" stroke-width="2"/>
    </svg>
</div>
