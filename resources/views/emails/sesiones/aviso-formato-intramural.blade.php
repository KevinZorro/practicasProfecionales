<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Sesiones sin formato intramural</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Buen día, {{ $destinatario->nombre }}:</p>

    @if ($paraDocente)
        <p>Estas sesiones suyas se acercan y el laboratorio todavía no tiene registrado el formato intramural
           (los insumos, equipos y simuladores que necesita). Si no lo ha entregado, hágalo llegar al laboratorio.</p>
    @else
        <p>Estas sesiones se acercan y todavía no tienen formato intramural registrado en la plataforma.</p>
    @endif

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
        <tr>
            <th align="left">Fecha</th>
            <th align="left">Hora</th>
            <th align="left">Caso clínico</th>
            <th align="left">Materia</th>
            @unless ($paraDocente)
                <th align="left">Docente</th>
            @endunless
        </tr>
        @foreach ($sesiones as $sesion)
            <tr>
                <td>{{ $sesion->fecha->format('d/m/Y') }}</td>
                <td>{{ substr($sesion->hora_inicio, 0, 5) }}</td>
                <td>{{ $sesion->casoClinico->nombre }}</td>
                <td>{{ $sesion->materia->nombre }}</td>
                @unless ($paraDocente)
                    <td>{{ $sesion->docente->nombre }}</td>
                @endunless
            </tr>
        @endforeach
    </table>

    <p>{{ config('app.name') }}</p>
</body>
</html>
