<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Resumen del log de nginx: el veredicto oficial del RNF01
|--------------------------------------------------------------------------
|
| El RNF01 dice "p95 <= 2 s medido en nginx ($request_time), sin 429 ni
| 5xx". Este script lee el log de acceso de producción (formato
| `con_tiempos` de docker/produccion/nginx.conf, que añade rt= y urt=) y
| saca, por grupo de ruta y en total, cuántas peticiones hubo, sus
| percentiles de rt y cuántas respondieron 2xx, 3xx, 4xx, 429 y 5xx.
|
| Uso (el log entra por la entrada estándar):
|
|   docker compose -f docker-compose.produccion.yml logs --no-log-prefix --since 10m web \
|     | php rendimiento/herramientas/resumir-nginx.php
|
|   ... | php rendimiento/herramientas/resumir-nginx.php --json > resumen.json
|
| Opciones:
|   --json              salida en JSON en vez de texto.
|   --limite-p95=2.0    umbral del veredicto, en segundos (2.0 por defecto, RNF01).
|   --solo-paginas      el veredicto y el total cuentan solo GET que no son de
|                       /build ni /storage (las páginas y el JSON del calendario).
|
| Variables: ninguna; todo entra por opciones.
|
| Las líneas que no son de acceso (avisos de error_log, líneas de docker,
| líneas en el formato viejo sin rt=) se descartan y se cuentan aparte.
| Los percentiles son de rango más cercano: el valor de la muestra, sin
| interpolar, que es lo que pide una medida sobre datos reales.
|
*/

const LIMITE_P95_POR_DEFECTO = 2.0;

$opciones = getopt('', ['json', 'solo-paginas', 'limite-p95::']);
$comoJson = array_key_exists('json', $opciones);
$soloPaginas = array_key_exists('solo-paginas', $opciones);
$limiteP95 = isset($opciones['limite-p95']) && is_numeric($opciones['limite-p95'])
    ? (float) $opciones['limite-p95']
    : LIMITE_P95_POR_DEFECTO;

/**
 * Ids numéricos y query fuera, para que /panel/solicitudes/12 y /panel/solicitudes/97
 * sean el mismo grupo.
 */
function grupoDeRuta(string $metodo, string $destino): string
{
    $ruta = parse_url($destino, PHP_URL_PATH);
    $ruta = is_string($ruta) && $ruta !== '' ? $ruta : '/';
    $ruta = (string) preg_replace('#/\d+(?=/|$)#', '/{id}', $ruta);

    return $metodo.' '.$ruta;
}

/** @param  list<float>  $ordenados */
function percentil(array $ordenados, float $p): float
{
    $n = count($ordenados);

    if ($n === 0) {
        return 0.0;
    }

    $rango = (int) ceil($p / 100 * $n);

    return $ordenados[max(0, min($n - 1, $rango - 1))];
}

/**
 * @return array{cantidad: int, p50: float, p90: float, p95: float, p99: float, max: float, urt_p95: float, 2xx: int, 3xx: int, 4xx: int, 429: int, 5xx: int}
 */
function resumir(array $grupo): array
{
    $tiempos = $grupo['rt'];
    sort($tiempos);
    $upstream = $grupo['urt'];
    sort($upstream);

    return [
        'cantidad' => count($tiempos),
        'p50' => percentil($tiempos, 50),
        'p90' => percentil($tiempos, 90),
        'p95' => percentil($tiempos, 95),
        'p99' => percentil($tiempos, 99),
        'max' => $tiempos === [] ? 0.0 : end($tiempos),
        'urt_p95' => percentil($upstream, 95),
        '2xx' => $grupo['2xx'],
        '3xx' => $grupo['3xx'],
        '4xx' => $grupo['4xx'],
        '429' => $grupo['429'],
        '5xx' => $grupo['5xx'],
    ];
}

function grupoVacio(): array
{
    return ['rt' => [], 'urt' => [], '2xx' => 0, '3xx' => 0, '4xx' => 0, '429' => 0, '5xx' => 0];
}

function anotar(array &$grupo, int $estado, float $rt, ?float $urt): void
{
    $grupo['rt'][] = $rt;

    if ($urt !== null) {
        $grupo['urt'][] = $urt;
    }

    match (true) {
        $estado === 429 => $grupo['429']++,
        $estado >= 500 => $grupo['5xx']++,
        $estado >= 400 => $grupo['4xx']++,
        $estado >= 300 => $grupo['3xx']++,
        default => $grupo['2xx']++,
    };
}

$patron = '/^(\S+) - (\S+) \[[^\]]+\] "(\S+) (\S+)[^"]*" (\d{3}) \d+ "[^"]*" "[^"]*" rt=([\d.]+) urt=(.*)$/';

$grupos = [];
$total = grupoVacio();
$leidas = 0;
$descartadas = 0;

while (($linea = fgets(STDIN)) !== false) {
    $linea = rtrim($linea, "\r\n");

    if ($linea === '') {
        continue;
    }

    $leidas++;

    if (preg_match($patron, $linea, $c) !== 1) {
        $descartadas++;

        continue;
    }

    [, , , $metodo, $destino, $estado, $rt, $urtCrudo] = $c;
    $ruta = parse_url($destino, PHP_URL_PATH);

    if ($soloPaginas && ($metodo !== 'GET' || (is_string($ruta) && preg_match('#^/(build|storage)/#', $ruta) === 1))) {
        continue;
    }

    // Con varios upstream nginx escribe "0.010, 0.020"; se suma. "-" es que no pasó por PHP.
    $partes = array_filter(array_map('trim', explode(',', $urtCrudo)), static fn (string $p): bool => is_numeric($p));
    $urt = $partes === [] ? null : array_sum(array_map('floatval', $partes));

    $clave = grupoDeRuta($metodo, $destino);
    $grupos[$clave] ??= grupoVacio();
    anotar($grupos[$clave], (int) $estado, (float) $rt, $urt);
    anotar($total, (int) $estado, (float) $rt, $urt);
}

ksort($grupos);

$resumenes = array_map(resumir(...), $grupos);
$resumenTotal = resumir($total);

$cumpleP95 = $resumenTotal['cantidad'] > 0 && $resumenTotal['p95'] <= $limiteP95;
$sinRechazos = $resumenTotal['429'] === 0 && $resumenTotal['5xx'] === 0;
$veredicto = $resumenTotal['cantidad'] === 0 ? 'SIN DATOS' : ($cumpleP95 && $sinRechazos ? 'CUMPLE' : 'NO CUMPLE');

if ($comoJson) {
    echo json_encode([
        'escenario' => 'RNF01',
        'limite_p95_segundos' => $limiteP95,
        'solo_paginas' => $soloPaginas,
        'lineas_leidas' => $leidas,
        'lineas_descartadas' => $descartadas,
        'veredicto' => $veredicto,
        'total' => $resumenTotal,
        'grupos' => $resumenes,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

    exit($veredicto === 'NO CUMPLE' ? 2 : 0);
}

$fila = static fn (string $nombre, array $r): string => sprintf(
    "%-52s %7d %7.3f %7.3f %7.3f %7.3f %7.3f %7.3f %6d %5d %5d %5d %5d\n",
    mb_strimwidth($nombre, 0, 52, '…'),
    $r['cantidad'], $r['p50'], $r['p90'], $r['p95'], $r['p99'], $r['max'], $r['urt_p95'],
    $r['2xx'], $r['3xx'], $r['4xx'], $r['429'], $r['5xx'],
);

echo sprintf("%-52s %7s %7s %7s %7s %7s %7s %7s %6s %5s %5s %5s %5s\n", 'grupo', 'n', 'p50', 'p90', 'p95', 'p99', 'max', 'urtp95', '2xx', '3xx', '4xx', '429', '5xx');
echo str_repeat('-', 148)."\n";

foreach ($resumenes as $nombre => $resumen) {
    echo $fila($nombre, $resumen);
}

echo str_repeat('-', 148)."\n";
echo $fila('TOTAL', $resumenTotal);
echo "\nTiempos en segundos (rt = \$request_time; urtp95 = p95 de \$upstream_response_time, lo que tardó PHP-FPM).\n";
echo sprintf("Líneas leídas: %d; descartadas por no ser de acceso con rt=: %d.\n", $leidas, $descartadas);
echo sprintf(
    "RNF01%s: p95 %.3f s (límite %.3f s), 429: %d, 5xx: %d -> %s\n",
    $soloPaginas ? ' (solo páginas)' : '',
    $resumenTotal['p95'], $limiteP95, $resumenTotal['429'], $resumenTotal['5xx'], $veredicto,
);

exit($veredicto === 'NO CUMPLE' ? 2 : 0);
