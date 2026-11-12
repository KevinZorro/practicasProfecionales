#!/usr/bin/env bash
# EXPLAIN (ANALYZE, BUFFERS) de cada consulta caliente, con un resumen de lo
# que merece mirarse.
#
# Tipo de prueba: análisis de comportamiento temporal por consulta (apoya a
# la prueba de carga; ISO/IEC 25010 eficiencia de desempeño). No mide
# capacidad: dice por qué una consulta tarda lo que tarda.
#
# Cada script de consultas/ se "renderiza" (se resuelven sus \set con valores
# al azar y se sustituyen en el SQL), se ejecuta una vez para calentar la
# caché y después con EXPLAIN. Todo corre en una transacción que se deshace,
# así que no cambia nada (los UPDATE/DELETE de sesión incluidos).
#
#   PGHOST=127.0.0.1 PGUSER=laboratorio PGPASSWORD=secret PGDATABASE=laboratorio_rendimiento \
#     ./rendimiento/base-de-datos/explicar.sh
#
# Variables (además de las PG* de comun.sh):
#   CONSULTAS      carpeta con los .sql (consultas/; para la base x5, consultas-x5/)
#   ESCENARIOS     nombres sin .sql separados por espacios (todos por defecto)
#   MUESTRAS       cuántas veces se renderiza cada uno con parámetros distintos (1)
#   SEMILLA        semilla de los parámetros al azar (42)
#   UMBRAL_MS      una sentencia es lenta si su Execution Time lo pasa (50)
#   GRANDES_FILAS  una tabla es grande desde estas filas estimadas (5000)
#   DESVIACION     una estimación está desviada si real y estimada difieren por este factor (10)
#
# Salida en rendimiento/resultados/base-de-datos/planes/<fecha>/:
#   <escenario>-m<k>.txt   los planes completos, uno por sentencia
#   resumen.csv            una fila por sentencia
#   problemas.txt          solo lo que marcó: Seq Scan en tablas grandes,
#                          estimaciones desviadas y tiempos sobre el umbral
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/comun.sh"

MUESTRAS="${MUESTRAS:-1}"
SEMILLA="${SEMILLA:-42}"
UMBRAL_MS="${UMBRAL_MS:-50}"
GRANDES_FILAS="${GRANDES_FILAS:-5000}"
DESVIACION="${DESVIACION:-10}"
CARPETA_CONSULTAS="${CONSULTAS:-$BASE_DE_DATOS/consultas}"

if [[ -z "${ESCENARIOS:-}" ]]; then
    ESCENARIOS="$(cd "$CARPETA_CONSULTAS" && ls ./*.sql | sed 's#^\./##; s#\.sql$##' | tr '\n' ' ')"
fi

sello="$(date +%Y%m%d-%H%M%S)"
carpeta="$RESULTADOS/planes/$sello"
mkdir -p "$carpeta"
trabajo="$(mktemp -d)"
chmod 755 "$trabajo"
trap 'rm -rf "$trabajo"; limpiar_sesiones' EXIT

# ANALYZE antes de medir: con estadísticas viejas las "estimaciones desviadas" serían ruido.
psql_silencioso -c "ANALYZE" >/dev/null 2>&1 || echo "Aviso: no se pudo correr ANALYZE con este usuario; las estimaciones pueden estar desactualizadas." >&2
preparar_sesiones

# Filas estimadas por tabla, para decidir cuáles son grandes.
psql_silencioso -F ' ' -c "SELECT relname, reltuples::bigint FROM pg_class WHERE relkind = 'r' AND relnamespace = 'public'::regnamespace" >"$trabajo/tablas.txt"

# Resuelve los \set de un script de pgbench (random(a, b), con * k o + k
# opcionales) y deja el SQL con los valores puestos, una sentencia por línea.
renderizar() {
    awk -v semilla="$1" '
        BEGIN { srand(semilla) }
        function numero(texto) { gsub(/[^0-9-]/, "", texto); return texto + 0 }
        function evaluar(expr,    t, p, r, resto) {
            if (match(expr, /random\([-0-9]+, *[-0-9]+\)/)) {
                t = substr(expr, RSTART + 7, RLENGTH - 8)
                split(t, p, /, */)
                r = p[1] + int(rand() * (p[2] - p[1] + 1))
                resto = substr(expr, RSTART + RLENGTH)
                if (match(resto, /\* *[0-9]+/)) r = r * numero(substr(resto, RSTART + 1))
                return r
            }
            return numero(expr)
        }
        /^\\set / { variable[$2] = evaluar(substr($0, index($0, $3))); next }
        /^--/ || /^[[:space:]]*$/ { next }
        {
            # Una sentencia puede ocupar varias líneas (el SQL a mano de Laravel): se junta hasta el ";" final.
            acumulado = acumulado (acumulado == "" ? "" : " ") $0
            if ($0 !~ /;[[:space:]]*$/) next
            linea = acumulado; acumulado = ""; salida = ""
            while (match(linea, /:[A-Za-z_][A-Za-z0-9_]*/)) {
                nombre = substr(linea, RSTART + 1, RLENGTH - 1)
                previo = (RSTART > 1) ? substr(linea, RSTART - 1, 1) : ""
                if (previo != ":" && (nombre in variable)) {
                    salida = salida substr(linea, 1, RSTART - 1) variable[nombre]
                } else {
                    salida = salida substr(linea, 1, RSTART + RLENGTH - 1)
                }
                linea = substr(linea, RSTART + RLENGTH)
            }
            print salida linea
        }
    ' "$2"
}

echo "escenario,muestra,sentencia,ejecucion_ms,planificacion_ms,lenta,seq_scan_grandes,estimaciones_desviadas" >"$carpeta/resumen.csv"
: >"$carpeta/problemas.txt"

for nombre in $ESCENARIOS; do
    for ((m = 1; m <= MUESTRAS; m++)); do
        sentencias="$trabajo/$nombre-m$m.sql"
        renderizar $((SEMILLA + m)) "$CARPETA_CONSULTAS/$nombre.sql" >"$sentencias"
        guion="$trabajo/guion-$nombre-m$m.sql"

        {
            # Calentamiento: las mismas sentencias, sin EXPLAIN y sin mostrar filas.
            echo '\o /dev/null'
            echo 'BEGIN;'
            cat "$sentencias"
            echo 'ROLLBACK;'
            echo '\o'
            echo 'BEGIN;'
            n=0
            while IFS= read -r sentencia; do
                n=$((n + 1))
                echo "\\echo '## $nombre $m $n'"
                echo "\\echo '-- ${sentencia//\'/\'\'}'"
                echo "EXPLAIN (ANALYZE, BUFFERS, SETTINGS) ${sentencia}"
            done <"$sentencias"
            echo 'ROLLBACK;'
        } >"$guion"

        planes="$carpeta/$nombre-m$m.txt"
        # psql lee el guion desde la carpeta de trabajo: se monta aparte si corre en contenedor.
        if [[ "$USAR_LOCAL" == "si" ]]; then
            herramienta psql -X -q -At -f "$guion" >"$planes" 2>&1 || echo "  (error en $nombre)" >&2
        else
            docker run --rm --network host --user "$(id -u):$(id -g)" \
                -e PGHOST -e PGPORT -e PGUSER -e PGPASSWORD -e PGDATABASE \
                -v "$trabajo:/trabajo:ro" "$PG_IMAGEN" psql -X -q -At -f "/trabajo/guion-$nombre-m$m.sql" >"$planes" 2>&1 || echo "  (error en $nombre)" >&2
        fi

        # Resumen por sentencia.
        awk -v umbral="$UMBRAL_MS" -v grandes="$GRANDES_FILAS" -v factor="$DESVIACION" -v tablas="$trabajo/tablas.txt" -v csv="$carpeta/resumen.csv" -v prob="$carpeta/problemas.txt" '
            BEGIN { while ((getline linea < tablas) > 0) { split(linea, t, " "); filas[t[1]] = t[2] } }
            function cerrar() {
                if (id == "") return
                lenta = (ejecucion + 0 > umbral) ? "si" : "no"
                printf "%s,%s,%s,%s,%s,%s,\"%s\",\"%s\"\n", escenario, muestra, sentencia, ejecucion, planificacion, lenta, seq, desv >> csv
                if (lenta == "si" || seq != "" || desv != "") {
                    printf "[%s m%s #%s] ejecución %s ms, planificación %s ms\n", escenario, muestra, sentencia, ejecucion, planificacion >> prob
                    if (lenta == "si") printf "  - lenta: supera %s ms\n", umbral >> prob
                    if (seq != "") printf "  - Seq Scan en tabla grande: %s\n", seq >> prob
                    if (desv != "") printf "  - estimación de filas desviada (estimadas->reales): %s\n", desv >> prob
                    printf "  %s\n\n", resumenSql >> prob
                }
            }
            /^## / { cerrar(); id = $0; escenario = $2; muestra = $3; sentencia = $4; limite = 0; ejecucion = ""; planificacion = ""; seq = ""; desv = ""; resumenSql = ""; next }
            /^-- / { resumenSql = substr($0, 4, 220); next }
            # Bajo un Limit los nodos se detienen antes de producir todo lo estimado: la sobreestimación no es un defecto.
            /^ *(->  )?Limit / { limite = 1 }
            /Execution Time:/ { ejecucion = $3 }
            /Planning Time:/ { planificacion = $3 }
            /Seq Scan on / {
                tabla = $0; sub(/.*Seq Scan on /, "", tabla); sub(/ .*/, "", tabla)
                if ((tabla in filas) && filas[tabla] + 0 >= grandes) seq = seq (seq == "" ? "" : " ") tabla "(" filas[tabla] " filas)"
            }
            /\(cost=.*rows=[0-9]+ width=[0-9]+\) \(actual time=.* rows=[0-9.]+ loops=[0-9]+\)/ {
                est = $0; sub(/.*\(cost=[^ ]* rows=/, "", est); sub(/ .*/, "", est)
                real = $0; sub(/.*\(actual time=[^ ]* rows=/, "", real); sub(/ .*/, "", real)
                maximo = (est + 0 > real + 0) ? est + 0 : real + 0
                if (maximo >= 100 && (((est + 1) / (real + 1) >= factor && !limite) || (real + 1) / (est + 1) >= factor)) {
                    nodo = $0; sub(/^[ ]*(->)?[ ]*/, "", nodo); sub(/ \(cost.*/, "", nodo)
                    desv = desv (desv == "" ? "" : "; ") nodo ": " est "->" real
                }
            }
            END { cerrar() }
        ' "$planes"
    done
    echo "  $nombre" >&2
done

echo >&2
echo "Planes: $carpeta" >&2
echo "Resumen: $carpeta/resumen.csv" >&2
echo "Para revisar: $carpeta/problemas.txt ($(grep -c '^\[' "$carpeta/problemas.txt" || true) sentencias marcadas)" >&2
