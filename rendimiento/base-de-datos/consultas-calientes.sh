#!/usr/bin/env bash
# pgbench con las consultas calientes de la aplicación (consultas/*.sql).
#
# Tipos de prueba (ISTQB CT-PT): carga (cada consulta aislada, de 1 a 20
# clientes) y concurrencia (la mezcla ponderada, que se parece a un día de
# uso). 20 clientes = pm.max_children de PHP-FPM: es el máximo de
# conexiones que la aplicación puede tener abiertas a la vez.
# Medidas ISO/IEC 25023: PTb-1-G tiempo medio de respuesta (media y
# percentiles p50/p95/p99), PTb-5-G rendimiento medio (TPS), PCa-1-G
# capacidad de procesamiento de transacciones.
#
# Mide la base, no la aplicación: sin PHP, sin nginx, sin red entre el
# navegador y el servidor. Lo que sale es el piso del tiempo de respuesta.
#
# Contra un PostgreSQL local o el SUT (con el override, puerto 55432):
#   PGHOST=127.0.0.1 PGPORT=5432 PGUSER=laboratorio PGPASSWORD=secret \
#     PGDATABASE=laboratorio_rendimiento ./rendimiento/base-de-datos/consultas-calientes.sh
#   PGPORT=55432 PGUSER=<DB_USERNAME> PGPASSWORD=<DB_PASSWORD> PGDATABASE=<DB_DATABASE> ./rendimiento/base-de-datos/consultas-calientes.sh
#
# Variables (además de las PG* de comun.sh):
#   CLIENTES      lista de clientes ("1 10 20")
#   DURACION      segundos por corrida (30)
#   HILOS         máximo de hilos -j (4)
#   CONSULTAS     carpeta con los .sql (consultas/, capturada sobre el volumen x1; para x5 usa consultas-x5/)
#   ESCENARIOS    nombres (sin .sql) separados por espacios; todos por defecto
#   MEZCLA        "si" corre además la mezcla ponderada (si)
#   SOLO_MEZCLA   "si" omite las corridas de cada escenario y mide solo la mezcla (no)
#   ESTADISTICAS  "si" reinicia pg_stat_statements antes y guarda su lectura al final (necesita el dueño)
#   CONSERVAR_REGISTROS  "si" no borra los registros por transacción (pesan decenas de MB)
#
# Escribe en rendimiento/resultados/base-de-datos/consultas-calientes-<fecha>/
# resumen.csv, con una fila por escenario y número de clientes.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/comun.sh"

CLIENTES="${CLIENTES:-1 10 20}"
DURACION="${DURACION:-30}"
HILOS="${HILOS:-4}"
MEZCLA="${MEZCLA:-si}"
SOLO_MEZCLA="${SOLO_MEZCLA:-no}"
ESTADISTICAS="${ESTADISTICAS:-no}"
CONSERVAR_REGISTROS="${CONSERVAR_REGISTROS:-no}"
CARPETA_CONSULTAS="${CONSULTAS:-$BASE_DE_DATOS/consultas}"

if [[ -z "${ESCENARIOS:-}" ]]; then
    ESCENARIOS="$(cd "$CARPETA_CONSULTAS" && ls ./*.sql | sed 's#^\./##; s#\.sql$##' | tr '\n' ' ')"
fi

# Pesos de la mezcla: cuántas veces ocurre cada cosa por cada cien
# peticiones al panel, a ojo, con 700 estudiantes y 150 docentes. Toda
# petición paga la base (sesión, usuario y roles); el resto, según la pantalla.
# Los cuatro escenarios sueltos que componen peticion-base no entran aparte.
declare -A PESOS=(
    [peticion-base]=90
    [calendario-docente]=10 [calendario-estudiante]=10 [calendario-coordinador]=3
    [resumen-docente]=12 [resumen-estudiante]=12 [resumen-coordinador]=4 [resumen-administrativo]=4 [resumen-supervisor]=1
    [historial-docente]=4
    [bandeja-pendientes]=3 [bandeja-revisadas]=3 [bandeja-vencidas]=1
    [inventario-disponibilidad]=3 [inventario-sin-cobertura]=1
    [cruces-docente]=2 [cruces-estudiantes]=2 [participacion-lista]=2
    [reporte-uso-escenarios]=1 [reporte-resultados-evaluacion]=1
    [bitacora-pagina]=2 [bitacora-filtrada]=1
    [sesion-gc]=2
)

sello="$(date +%Y%m%d-%H%M%S)"
carpeta="$RESULTADOS/consultas-calientes-$sello"
mkdir -p "$carpeta/salidas" "$carpeta/registros"
resumen="$carpeta/resumen.csv"
echo "escenario,clientes,duracion_s,tps,latencia_media_ms,latencia_desv_ms,p50_ms,p95_ms,p99_ms,max_ms,transacciones,fallidas,estado" >"$resumen"

trap limpiar_sesiones EXIT
preparar_sesiones

if [[ "$ESTADISTICAS" == "si" ]]; then
    "$BASE_DE_DATOS/estadisticas.sh" reiniciar >&2
fi

hilos_para() { local c="$1"; echo $(( c < HILOS ? c : HILOS )); }

# Saca de la salida de pgbench el valor de una línea "clave = valor ms".
valor() { sed -n "s/^$1 = \([0-9.]*\).*/\1/p" <<<"$2" | head -1; }

correr() {
    local nombre="$1" c="$2"; shift 2
    local prefijo="$carpeta/registros/$nombre-c$c"
    local salida estado=ok
    salida="$(herramienta pgbench -n -c "$c" -j "$(hilos_para "$c")" -T "$DURACION" -P 10 \
        --log --log-prefix="$(ruta_para_herramienta "$prefijo")" "$@" 2>&1)" || estado="error($?)"
    echo "$salida" >"$carpeta/salidas/$nombre-c$c.txt"

    local tps media desv trans fallidas
    tps="$(valor tps "$salida")"
    media="$(valor 'latency average' "$salida")"
    desv="$(valor 'latency stddev' "$salida")"
    trans="$(sed -n 's/^number of transactions actually processed: \([0-9]*\).*/\1/p' <<<"$salida" | head -1)"
    fallidas="$(sed -n 's/^number of failed transactions: \([0-9]*\).*/\1/p' <<<"$salida" | head -1)"

    # Los registros por transacción dan los percentiles; sin ellos solo hay media.
    local registros=("$prefijo".*)
    if [[ -e "${registros[0]}" ]]; then
        if [[ "$nombre" == "mezcla" ]]; then
            percentiles "${registros[@]}" >"$carpeta/registros/$nombre-c$c.percentiles"
            local general
            general="$(cat "${registros[@]}" | awk '{print 0, $3}' | sort -k2,2n | awk '{v[NR]=$2} END {printf "%.3f %.3f %.3f %.3f", v[int(NR*0.5+0.999999)]/1000, v[int(NR*0.95+0.999999)]/1000, v[int(NR*0.99+0.999999)]/1000, v[NR]/1000}')"
            read -r p50 p95 p99 maximo <<<"$general"
        else
            read -r _ _ p50 p95 p99 maximo < <(percentiles "${registros[@]}")
        fi
        [[ "$CONSERVAR_REGISTROS" == "si" ]] || rm -f "${registros[@]}"
    else
        p50="" p95="" p99="" maximo=""
    fi
    echo "$nombre,$c,$DURACION,${tps:-},${media:-},${desv:-},${p50:-},${p95:-},${p99:-},${maximo:-},${trans:-},${fallidas:-0},$estado" >>"$resumen"
    printf '  %-34s c=%-3s tps=%-9s media=%-8s p95=%-8s p99=%-8s %s\n' "$nombre" "$c" "${tps:-?}" "${media:-?}" "${p95:-?}" "${p99:-?}" "$estado" >&2
}

echo "Escenarios: $ESCENARIOS" >&2
echo "Clientes: $CLIENTES, ${DURACION}s por corrida" >&2

if [[ "$SOLO_MEZCLA" != "si" ]]; then
    for c in $CLIENTES; do
        for nombre in $ESCENARIOS; do
            correr "$nombre" "$c" -f "$(ruta_para_herramienta "$CARPETA_CONSULTAS/$nombre.sql")"
        done
    done
fi

if [[ "$MEZCLA" == "si" ]]; then
    argumentos=()
    nombres=()
    for nombre in "${!PESOS[@]}"; do
        # Solo entran los de ESCENARIOS que tengan peso.
        if [[ " $ESCENARIOS " == *" $nombre "* ]]; then
            argumentos+=(-f "$(ruta_para_herramienta "$CARPETA_CONSULTAS/$nombre.sql")@${PESOS[$nombre]}")
            nombres+=("$nombre")
        fi
    done
    for c in $CLIENTES; do
        correr mezcla "$c" "${argumentos[@]}"
        # Percentiles por escenario dentro de la mezcla: el número de script es el orden de los -f.
        if [[ -s "$carpeta/registros/mezcla-c$c.percentiles" ]]; then
            while read -r indice n p50 p95 p99 maximo; do
                echo "mezcla:${nombres[$indice]},$c,$DURACION,$(awk -v n="$n" -v d="$DURACION" 'BEGIN{printf "%.2f", n/d}'),,,$p50,$p95,$p99,$maximo,$n,," >>"$resumen"
            done <"$carpeta/registros/mezcla-c$c.percentiles"
        fi
    done
fi

if [[ "$ESTADISTICAS" == "si" ]]; then
    "$BASE_DE_DATOS/estadisticas.sh" leer >"$carpeta/estadisticas.txt" 2>&1 || true
fi

[[ "$CONSERVAR_REGISTROS" == "si" ]] || rmdir "$carpeta/registros" 2>/dev/null || true
echo "Resumen: $resumen" >&2
