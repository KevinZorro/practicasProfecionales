<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Sincronización detenida</title>
</head>
<body style="font-family: sans-serif; color: #1f2937; line-height: 1.5;">
    <p>Buen día:</p>

    <p>La sincronización con la base institucional <strong>se detuvo sin aplicar ningún cambio</strong>.
       Iba a desactivar {{ $porDesactivar }} de {{ $activas }} cuentas activas, más del {{ $umbral }} % permitido.</p>

    <p>Suele pasar cuando la vista institucional llega vacía o a medio cargar. Revise con sistemas que la base esté
       completa; la siguiente pasada lo intentará de nuevo. Si las desactivaciones son reales (por ejemplo, al cierre
       del semestre), suba el umbral en la variable <code>SINCRONIZACION_UMBRAL_DESACTIVACION</code> para esa pasada.</p>

    <p>{{ config('app.name') }}</p>
</body>
</html>
