#!/usr/bin/env bash
# Muestreo de recursos de los contenedores del SUT (ISO/IEC 25023: PRu-1-G uso
# medio de procesador, PRu-2-G uso medio de memoria, PRu-3-G uso medio de E/S).
#
# Cada INTERVALO segundos lee `docker stats --no-stream` y agrega una línea
# JSON por contenedor, con marca de tiempo, a
# rendimiento/resultados/recursos/<nombre>.jsonl. Se lanza en segundo plano
# durante una prueba y se detiene con una señal (TERM o INT):
#
#   rendimiento/herramientas/muestrear-recursos.sh &
#   PID=$!
#   ... correr la prueba ...
#   kill "$PID"; wait "$PID" 2>/dev/null || true
#
# Modo resumen (CPU media/p95/máx y memoria media/máx por contenedor):
#
#   rendimiento/herramientas/muestrear-recursos.sh --resumen rendimiento/resultados/recursos/recursos-AAAA.jsonl
#
# Variables:
#   INTERVALO    segundos entre muestras (2).
#   PROYECTO     proyecto de compose a muestrear (el que levantó docker-compose.produccion.yml;
#                se toma de las etiquetas com.docker.compose.project; vacío = filtrar por FILTRO).
#   FILTRO       filtro de `docker ps` para elegir contenedores si PROYECTO está vacío
#                ("name=laboratorio" por defecto).
#   SALIDA       archivo de salida (resultados/recursos/recursos-<fecha>.jsonl).
#
# El CPU de docker stats es porcentaje de UN núcleo (200 % = dos núcleos llenos).
# La memoria se guarda en bytes, sin el límite.

set -euo pipefail

RAIZ="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
INTERVALO="${INTERVALO:-2}"
PROYECTO="${PROYECTO:-}"
FILTRO="${FILTRO:-name=laboratorio}"

# "12.5MiB" / "1.2GiB" / "800kB" -> bytes (docker stats usa unidades mixtas).
convertir_a_bytes() {
    awk -v v="$1" 'BEGIN {
        n = v + 0; u = v; gsub(/[0-9.]/, "", u)
        f = 1
        if (u == "KiB") f = 1024; else if (u == "MiB") f = 1048576; else if (u == "GiB") f = 1073741824
        else if (u == "kB" || u == "KB") f = 1000; else if (u == "MB") f = 1000000; else if (u == "GB") f = 1000000000
        printf "%.0f", n * f
    }'
}

resumir() {
    local archivo="$1"
    [[ -f "$archivo" ]] || { echo "No existe $archivo" >&2; exit 1; }
    php -r '
        $porContenedor = [];
        foreach (file($argv[1], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
            $m = json_decode($linea, true);
            if (!is_array($m) || !isset($m["contenedor"])) { continue; }
            $porContenedor[$m["contenedor"]]["cpu"][] = (float) $m["cpu_porcentaje"];
            $porContenedor[$m["contenedor"]]["mem"][] = (float) $m["memoria_bytes"];
            $porContenedor[$m["contenedor"]]["lectura"][] = (float) $m["bloque_lectura_bytes"];
            $porContenedor[$m["contenedor"]]["escritura"][] = (float) $m["bloque_escritura_bytes"];
        }
        $p = function (array $v, float $q): float { sort($v); return $v === [] ? 0.0 : $v[max(0, (int) ceil($q / 100 * count($v)) - 1)]; };
        $media = fn (array $v): float => $v === [] ? 0.0 : array_sum($v) / count($v);
        $mib = fn (float $b): string => sprintf("%.1f", $b / 1048576);
        printf("%-34s %6s %9s %9s %9s %10s %10s %12s %12s\n", "contenedor", "n", "cpu_med%", "cpu_p95%", "cpu_max%", "mem_med_MiB", "mem_max_MiB", "E/S_lect_MiB", "E/S_escr_MiB");
        ksort($porContenedor);
        foreach ($porContenedor as $nombre => $d) {
            // La E/S de bloque de docker stats es acumulada: lo que importa es lo que creció en la prueba.
            printf("%-34s %6d %9.1f %9.1f %9.1f %10s %10s %12s %12s\n", $nombre, count($d["cpu"]), $media($d["cpu"]), $p($d["cpu"], 95), max($d["cpu"]),
                $mib($media($d["mem"])), $mib(max($d["mem"])), $mib(max($d["lectura"]) - min($d["lectura"])), $mib(max($d["escritura"]) - min($d["escritura"])));
        }
    ' "$archivo"
}

if [[ "${1:-}" == "--resumen" ]]; then
    resumir "${2:?Uso: $0 --resumen archivo.jsonl}"
    exit 0
fi

SALIDA="${SALIDA:-$RAIZ/rendimiento/resultados/recursos/recursos-$(date +%Y%m%dT%H%M%S).jsonl}"
mkdir -p "$(dirname "$SALIDA")"

contenedores() {
    if [[ -n "$PROYECTO" ]]; then
        docker ps -q --filter "label=com.docker.compose.project=$PROYECTO"
    else
        docker ps -q --filter "$FILTRO"
    fi
}

detener=0
trap 'detener=1' TERM INT

echo "Muestreando cada ${INTERVALO}s en $SALIDA (kill $$ para detener)" >&2

while ((detener == 0)); do
    ids="$(contenedores | tr '\n' ' ')"

    if [[ -n "${ids// /}" ]]; then
        marca="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
        # shellcheck disable=SC2086
        docker stats --no-stream --format '{{json .}}' $ids 2>/dev/null | while IFS= read -r linea; do
            nombre="$(jq -r '.Name' <<<"$linea")"
            cpu="$(jq -r '.CPUPerc' <<<"$linea" | tr -d '%')"
            memoria="$(jq -r '.MemUsage' <<<"$linea" | awk -F' / ' '{print $1}')"
            bloques="$(jq -r '.BlockIO' <<<"$linea")"
            lectura="${bloques%% / *}"
            escritura="${bloques##* / }"

            printf '{"marca":"%s","contenedor":"%s","cpu_porcentaje":%s,"memoria_bytes":%s,"bloque_lectura_bytes":%s,"bloque_escritura_bytes":%s,"crudo":%s}\n' \
                "$marca" "$nombre" "${cpu:-0}" "$(convertir_a_bytes "$memoria")" \
                "$(convertir_a_bytes "$lectura")" "$(convertir_a_bytes "$escritura")" "$linea" >>"$SALIDA"
        done
    fi

    # sleep en segundo plano + wait: la señal interrumpe la espera sin demora.
    sleep "$INTERVALO" &
    wait $! 2>/dev/null || true
done

echo "Muestreo detenido: $SALIDA" >&2
