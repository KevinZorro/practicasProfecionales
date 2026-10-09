@php($solicitud = $sustitucion->solicitud)
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Sesión por sustitución</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Buen día, {{ $sustitucion->docenteNuevo->nombre }}:</p>

    <p>Usted dictará esta sesión en reemplazo de {{ $sustitucion->docenteAnterior->nombre }}.</p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
        <tr><td><strong>Caso clínico</strong></td><td>{{ $solicitud->casoClinico->nombre }}</td></tr>
        <tr><td><strong>Materia</strong></td><td>{{ $solicitud->materia->nombre }}</td></tr>
        <tr><td><strong>Fecha</strong></td><td>{{ $solicitud->fecha->format('d/m/Y') }}</td></tr>
        <tr><td><strong>Hora</strong></td><td>{{ substr($solicitud->hora_inicio, 0, 5) }} a {{ substr($solicitud->hora_fin, 0, 5) }}</td></tr>
    </table>

    <p><strong>Novedad:</strong> {{ $sustitucion->motivo }}</p>

    <p>{{ config('app.name') }}</p>
</body>
</html>
