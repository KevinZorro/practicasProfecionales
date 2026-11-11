#!/usr/bin/env bash
# Línea base del hardware: la carga integrada tipo TPC-B de pgbench.
#
# Tipo de prueba: humo/línea base del hardware (ISTQB CT-PT).
# Medidas ISO/IEC 25023: PTb-5-G rendimiento medio (TPS) y PTb-1-G tiempo
# medio de respuesta (latencia media y desviación), PCa-1-G capacidad de
# procesamiento de transacciones, por número de clientes.
#
# Crea y borra su propia base (pgbench_referencia): nunca toca la de la
# aplicación. Necesita un usuario que pueda crear bases.
#
#   PGHOST=127.0.0.1 PGUSER=laboratorio PGPASSWORD=secret ./rendimiento/base-de-datos/referencia-tpcb.sh
#
# Variables (además de las PG* de comun.sh):
#   ESCALA     factor de escala de pgbench -i (50 por defecto: 5 millones de filas en pgbench_accounts)
#   DURACION   segundos por número de clientes (60)
#   CLIENTES   lista de clientes ("1 4 8 16")
#   HILOS      máximo de hilos -j (4); se usa min(clientes, HILOS)
#   BASE_REFERENCIA  nombre de la base temporal (pgbench_referencia)
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/comun.sh"

ESCALA="${ESCALA:-50}"
DURACION="${DURACION:-60}"
CLIENTES="${CLIENTES:-1 4 8 16}"
HILOS="${HILOS:-4}"
BASE_REFERENCIA="${BASE_REFERENCIA:-pgbench_referencia}"

if [[ "$BASE_REFERENCIA" == "${PGDATABASE}" ]]; then
    echo "La base de referencia no puede ser la de la aplicación." >&2
    exit 1
fi

mkdir -p "$RESULTADOS"
sello="$(date +%Y%m%d-%H%M%S)"
crudo="$RESULTADOS/referencia-tpcb-$sello.txt"
resumen="$RESULTADOS/referencia-tpcb-$sello.csv"

limpiar() {
    PGDATABASE=postgres psql_silencioso -c "DROP DATABASE IF EXISTS $BASE_REFERENCIA" >/dev/null 2>&1 || true
}
trap limpiar EXIT

# La base se crea desde "postgres", que existe siempre.
PGDATABASE=postgres psql_silencioso -c "DROP DATABASE IF EXISTS $BASE_REFERENCIA" -c "CREATE DATABASE $BASE_REFERENCIA" >/dev/null

echo "Inicializando pgbench (escala $ESCALA)..." >&2
PGDATABASE="$BASE_REFERENCIA" herramienta pgbench -i -q -s "$ESCALA" >>"$crudo" 2>&1

echo "clientes,hilos,duracion_s,tps,latencia_media_ms,latencia_desv_ms,transacciones,fallidas" >"$resumen"

for c in $CLIENTES; do
    hilos=$(( c < HILOS ? c : HILOS ))
    echo "== clientes=$c hilos=$hilos duracion=${DURACION}s ==" | tee -a "$crudo" >&2
    salida="$(PGDATABASE="$BASE_REFERENCIA" herramienta pgbench -c "$c" -j "$hilos" -T "$DURACION" -P 10 -r 2>&1)"
    echo "$salida" >>"$crudo"

    tps="$(sed -n 's/^tps = \([0-9.]*\).*/\1/p' <<<"$salida" | head -1)"
    media="$(sed -n 's/^latency average = \([0-9.]*\) ms.*/\1/p' <<<"$salida" | head -1)"
    desv="$(sed -n 's/^latency stddev = \([0-9.]*\) ms.*/\1/p' <<<"$salida" | head -1)"
    trans="$(sed -n 's/^number of transactions actually processed: \([0-9]*\).*/\1/p' <<<"$salida" | head -1)"
    fallidas="$(sed -n 's/^number of failed transactions: \([0-9]*\).*/\1/p' <<<"$salida" | head -1)"
    echo "$c,$hilos,$DURACION,${tps:-},${media:-},${desv:-},${trans:-},${fallidas:-0}" >>"$resumen"
done

echo >&2
cat "$resumen" >&2
echo "Resumen: $resumen" >&2
echo "Salida completa: $crudo" >&2
