@php($solicitud = $preparacion->solicitud)
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $esCambio ? 'Cambio de sala' : 'Sala asignada' }}</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Buen día, {{ $solicitud->docente->nombre }}:</p>

    @if ($esCambio)
        <p>La sala de su sesión <strong>cambió</strong>. La nueva es:</p>
    @else
        <p>Su sesión ya tiene sala:</p>
    @endif

    <p style="font-size: 1.25em;"><strong>{{ $preparacion->sala->nombreCompleto() }}</strong></p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
        <tr>
            <td><strong>Caso clínico</strong></td>
            <td>{{ $solicitud->casoClinico->nombre }}</td>
        </tr>
        <tr>
            <td><strong>Materia</strong></td>
            <td>{{ $solicitud->materia->nombre }}</td>
        </tr>
        <tr>
            <td><strong>Fecha</strong></td>
            <td>{{ $solicitud->fecha->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Hora</strong></td>
            <td>{{ $solicitud->hora_inicio }} a {{ $solicitud->hora_fin }}</td>
        </tr>
    </table>

    <p>{{ config('app.name') }}</p>
</body>
</html>
