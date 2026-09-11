{{--
    Cuerpo compartido de los tres reportes en PDF.

    Los encabezados y las filas llegan ya armados desde el exportador, que es
    el mismo que alimenta el Excel: aquí no se calcula ni se formatea nada,
    solo se pinta. dompdf no entiende Tailwind, así que este es uno de los
    pocos sitios del proyecto con CSS suelto.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .filtro { font-size: 9px; color: #6b7280; margin: 0 0 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
        tbody tr:nth-child(even) { background: #fafafa; }
        .vacio { color: #6b7280; font-style: italic; padding: 12px 0; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <p class="filtro">{{ $descripcionDelFiltro }} &middot; {{ $filas->count() }} registros</p>

    @if ($filas->isEmpty())
        <p class="vacio">No hay registros para este filtro.</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($encabezados as $encabezado)
                        <th>{{ $encabezado }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        @foreach ($fila as $celda)
                            <td>{{ $celda }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
