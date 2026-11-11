#!/usr/bin/env bash
# Funciones comunes de las pruebas de base de datos. Se carga con `source`,
# no se ejecuta.
#
# Conexión (las mismas variables de libpq):
#   PGHOST (127.0.0.1)  PGPORT (5432)  PGUSER (laboratorio)  PGPASSWORD  PGDATABASE
# Herramientas:
#   Por defecto corren en un contenedor postgres:16 (misma versión que
#   producción) con --network host, para no depender de lo instalado en el
#   equipo. USAR_LOCAL=si usa los psql y pgbench del PATH.
#   PG_IMAGEN cambia la imagen.
#   PG_DOCKER_OPCIONES añade opciones a `docker run` (p. ej. "--cpuset-cpus 3" para
#   fijar el generador de carga a su propia CPU; lo usa rendimiento/campana.sh).

set -euo pipefail

# Raíz de rendimiento/ (la carpeta que contiene este directorio).
RENDIMIENTO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BASE_DE_DATOS="$RENDIMIENTO/base-de-datos"

export PGHOST="${PGHOST:-127.0.0.1}"
export PGPORT="${PGPORT:-5432}"
export PGUSER="${PGUSER:-laboratorio}"
export PGPASSWORD="${PGPASSWORD:-}"
export PGDATABASE="${PGDATABASE:-laboratorio_rendimiento}"

PG_IMAGEN="${PG_IMAGEN:-postgres:16}"
USAR_LOCAL="${USAR_LOCAL:-no}"
RESULTADOS="${RESULTADOS:-$RENDIMIENTO/resultados/base-de-datos}"

# Ejecuta una herramienta de PostgreSQL (psql, pgbench, createdb...) con la
# conexión del entorno. Los archivos se ven dentro del contenedor en
# /rendimiento; los resultados se escriben con el usuario del anfitrión, no
# con root.
herramienta() {
    if [[ "$USAR_LOCAL" == "si" ]]; then
        "$@"
        return
    fi
    local extra=()
    read -ra extra <<<"${PG_DOCKER_OPCIONES:-}"
    docker run --rm --network host ${extra[@]+"${extra[@]}"} \
        --user "$(id -u):$(id -g)" \
        -e PGHOST -e PGPORT -e PGUSER -e PGPASSWORD -e PGDATABASE \
        -v "$RENDIMIENTO:/rendimiento" \
        "$PG_IMAGEN" "$@"
}

# Ruta de un archivo de rendimiento/ tal como la ve `herramienta`.
ruta_para_herramienta() {
    if [[ "$USAR_LOCAL" == "si" ]]; then
        echo "$1"
    else
        echo "/rendimiento${1#"$RENDIMIENTO"}"
    fi
}

psql_silencioso() {
    herramienta psql -X -q -v ON_ERROR_STOP=1 -At "$@"
}

# Percentiles de un registro de pgbench (--log). Cada línea es:
#   cliente transacción latencia_µs script epoch µs [retraso]
# Imprime "grupo n p50 p95 p99 máx" en milisegundos, un renglón por grupo
# (el grupo es el número de script, para la mezcla). Con awk portable
# (mawk o gawk): ordena afuera y reparte adentro.
percentiles() {
    local archivos=("$@")
    cat "${archivos[@]}" \
        | awk '{ print $4, $3 }' \
        | sort -k1,1n -k2,2n \
        | awk '
            { grupo[NR] = $1; valor[NR] = $2 }
            END {
                inicio = 1
                for (i = 1; i <= NR + 1; i++) {
                    if (i == NR + 1 || grupo[i] != grupo[inicio]) {
                        n = i - inicio
                        p50 = valor[inicio + int((n * 0.50 > 1 ? n * 0.50 : 1) + 0.999999) - 1]
                        p95 = valor[inicio + int((n * 0.95 > 1 ? n * 0.95 : 1) + 0.999999) - 1]
                        p99 = valor[inicio + int((n * 0.99 > 1 ? n * 0.99 : 1) + 0.999999) - 1]
                        printf "%s %d %.3f %.3f %.3f %.3f\n", grupo[inicio], n, p50 / 1000, p95 / 1000, p99 / 1000, valor[i - 1] / 1000
                        inicio = i
                    }
                }
            }'
}

# Mil sesiones de prueba (rend-1 .. rend-1000) con el tamaño de una real,
# para los escenarios sesion-* y peticion-base. Se pueden crear otra vez sin
# problema y se borran con limpiar_sesiones; no se toca ninguna otra.
preparar_sesiones() {
    psql_silencioso -c "INSERT INTO sessions (id, user_id, ip_address, user_agent, payload, last_activity)
        SELECT 'rend-' || g, NULL, '10.0.0.1', 'rendimiento', repeat('a', 1600), extract(epoch from now())::int
        FROM generate_series(1, 1000) g ON CONFLICT (id) DO NOTHING" >/dev/null
}

limpiar_sesiones() {
    psql_silencioso -c "DELETE FROM sessions WHERE id LIKE 'rend-%'" >/dev/null 2>&1 || true
}
