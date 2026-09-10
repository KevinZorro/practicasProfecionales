<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Solicitud aprobada</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Buen día, {{ $solicitud->docente->nombre }}:</p>

    <p>Su solicitud de escenario fue <strong>aprobada</strong>.</p>

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
            <td><strong>Tipo de sesión</strong></td>
            <td>{{ $solicitud->tipo->etiqueta() }}</td>
        </tr>
        <tr>
            <td><strong>Fecha</strong></td>
            <td>{{ $solicitud->fecha->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Hora</strong></td>
            <td>{{ $solicitud->hora_inicio }} a {{ $solicitud->hora_fin }}</td>
        </tr>
        <tr>
            <td><strong>Estudiantes</strong></td>
            <td>{{ $solicitud->cantidad_estudiantes }}</td>
        </tr>
    </table>

    {{-- La sala se asigna durante la preparación, poco antes de la clase. --}}
    <p>La sala se le informará el día de la práctica, cuando el equipo del
       laboratorio termine el montaje del escenario.</p>

    <p>{{ config('app.name') }}</p>
</body>
</html>
