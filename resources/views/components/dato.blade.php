{{-- Par etiqueta/valor de un detalle. La etiqueta es un dato, no un rótulo
     sobre un titular: por eso puede ir en mayúsculas pequeñas. --}}
@props(['etiqueta'])

<div {{ $attributes->class('min-w-0') }}>
    <dt class="text-[11px] font-semibold uppercase tracking-[0.08em] text-gray-500">{{ $etiqueta }}</dt>
    <dd class="mt-1 break-words text-[15px] leading-snug text-gray-900">{{ $slot }}</dd>
</div>
