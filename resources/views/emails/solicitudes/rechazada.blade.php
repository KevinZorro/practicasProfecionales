<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud rechazada</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Buen día, {{ $solicitud->docente->nombre }}:</p>

    <p>Su solicitud de escenario fue <strong>rechazada</strong>.</p>

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
            <td><strong>Fecha solicitada</strong></td>
            <td>{{ $solicitud->fecha->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Hora</strong></td>
            <td>{{ $solicitud->hora_inicio }} a {{ $solicitud->hora_fin }}</td>
        </tr>
    </table>

    @if ($solicitud->motivo_rechazo)
        <p><strong>Motivo:</strong> {{ $solicitud->motivo_rechazo }}</p>
    @else
        <p>No se registró un motivo. Puede consultarlo con la coordinación del
           laboratorio.</p>
    @endif

    <p>Puede radicar una solicitud nueva con otra fecha u otro horario.</p>

    <p>{{ config('app.name') }}</p>
</body>
</html>
