{{--
    Tabla en una pieza blanca. En el celular se desplaza en horizontal dentro
    de su pieza en vez de romper la página: el administrativo la consulta
    desde el celular. Sin cifras tabulares: en Onest separan el 1 de los
    demás dígitos y «15» se leía «1 5».
--}}
@props(['encabezados' => [], 'etiqueta' => null])

{{-- Con etiqueta, la pieza que se desplaza es una región con nombre y entra
     en el orden del teclado: sin esto, quien no usa mouse no puede ver las
     columnas que quedan fuera (QA-12). --}}
<div {{ $attributes->class('overflow-x-auto rounded-2xl bg-white') }}
     @if ($etiqueta) tabindex="0" role="region" aria-label="{{ $etiqueta }}" @endif>
    <table class="min-w-full text-left text-[14px]">
        @if ($encabezados !== [])
            <thead>
                <tr class="border-b border-gray-200">
                    @foreach ($encabezados as $encabezado)
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-[13px] font-semibold text-gray-500 first:pl-5 last:pr-5">
                            {{ $encabezado }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif

        <tbody class="divide-y divide-gray-100 [&_td:first-child]:pl-5 [&_td:last-child]:pr-5 [&_td]:px-4 [&_td]:py-3 [&_a]:inline-block [&_a]:py-0.5 [&_tr]:transition-colors [&_tr:hover]:bg-gray-100/60">
            {{ $slot }}
        </tbody>
    </table>
</div>
