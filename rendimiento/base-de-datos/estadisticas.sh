#!/usr/bin/env bash
# Estadísticas de PostgreSQL para leer antes y después de una prueba de carga.
#
# Mide utilización de recursos (ISO/IEC 25010; ISO/IEC 25023 PRu-3-G uso
# medio de E/S, por la razón de aciertos de caché y las lecturas) y señala
# qué consultas y qué tablas se llevan el tiempo.
#
#   ./estadisticas.sh reiniciar   pone en cero pg_stat_statements y las estadísticas de tablas e índices
#   ./estadisticas.sh leer        imprime el informe (por defecto)
#
# Necesita pg_stat_statements cargado (shared_preload_libraries): lo trae el
# override rendimiento/docker-compose.rendimiento.yml; en un PostgreSQL local
# hay que activarlo en postgresql.conf y reiniciarlo. La extensión se crea
# aquí con CREATE EXTENSION IF NOT EXISTS, y eso lo hace el dueño de la base:
# conéctate como DB_DUENO_USUARIO, no como el usuario de la aplicación.
#
#   PGPORT=55432 PGUSER=<dueño> PGPASSWORD=<...> PGDATABASE=<base> ./rendimiento/base-de-datos/estadisticas.sh reiniciar
#   ... correr la prueba ...
#   PGPORT=55432 PGUSER=<dueño> PGPASSWORD=<...> PGDATABASE=<base> ./rendimiento/base-de-datos/estadisticas.sh leer > informe.txt
#
# Variables: las PG* de comun.sh, y TOP (filas de cada lista, 20).
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/comun.sh"

TOP="${TOP:-20}"
orden="${1:-leer}"

psql_silencioso -c "CREATE EXTENSION IF NOT EXISTS pg_stat_statements" >/dev/null

if ! psql_silencioso -c "SELECT count(*) FROM pg_stat_statements" >/dev/null 2>&1; then
    echo "pg_stat_statements no está cargada: falta shared_preload_libraries=pg_stat_statements y reiniciar PostgreSQL." >&2
    exit 1
fi

if [[ "$orden" == "reiniciar" ]]; then
    psql_silencioso -c "SELECT pg_stat_statements_reset()" -c "SELECT pg_stat_reset()" >/dev/null
    echo "Estadísticas en cero: $(date -Iseconds)" >&2
    exit 0
fi

psql_informe() { herramienta psql -X -q -v ON_ERROR_STOP=1 -P pager=off "$@"; }

echo "# Estadísticas de $PGDATABASE en $PGHOST:$PGPORT, $(date -Iseconds)"

echo
echo "## Top $TOP consultas por tiempo total (ms)"
psql_informe -c "SELECT calls, round(total_exec_time::numeric, 1) AS total_ms, round(mean_exec_time::numeric, 3) AS media_ms,
        round(max_exec_time::numeric, 1) AS max_ms, rows, round((100 * total_exec_time / sum(total_exec_time) OVER ())::numeric, 1) AS pct,
        left(regexp_replace(query, '\s+', ' ', 'g'), 110) AS consulta
    FROM pg_stat_statements WHERE dbid = (SELECT oid FROM pg_database WHERE datname = current_database())
    ORDER BY total_exec_time DESC LIMIT $TOP"

echo
echo "## Top $TOP consultas por tiempo medio (ms), con al menos 5 llamadas"
psql_informe -c "SELECT calls, round(mean_exec_time::numeric, 3) AS media_ms, round(stddev_exec_time::numeric, 3) AS desv_ms,
        round(max_exec_time::numeric, 1) AS max_ms, rows, shared_blks_read AS bloques_leidos_disco,
        left(regexp_replace(query, '\s+', ' ', 'g'), 110) AS consulta
    FROM pg_stat_statements WHERE calls >= 5 AND dbid = (SELECT oid FROM pg_database WHERE datname = current_database())
    ORDER BY mean_exec_time DESC LIMIT $TOP"

echo
echo "## Tablas: lecturas secuenciales frente a por índice"
psql_informe -c "SELECT relname AS tabla, seq_scan, seq_tup_read, idx_scan, n_live_tup AS filas_vivas, n_dead_tup AS filas_muertas,
        CASE WHEN seq_scan + idx_scan = 0 THEN NULL ELSE round(100.0 * seq_scan / (seq_scan + idx_scan), 1) END AS pct_secuencial,
        last_autovacuum, last_autoanalyze
    FROM pg_stat_user_tables ORDER BY seq_tup_read DESC LIMIT $TOP"

echo
echo "## Índices sin uso (idx_scan = 0) desde el último reinicio de estadísticas, sin contar claves únicas ni primarias"
psql_informe -c "SELECT s.relname AS tabla, s.indexrelname AS indice, pg_size_pretty(pg_relation_size(s.indexrelid)) AS tamano
    FROM pg_stat_user_indexes s JOIN pg_index i ON i.indexrelid = s.indexrelid
    WHERE s.idx_scan = 0 AND NOT i.indisunique AND NOT i.indisprimary
    ORDER BY pg_relation_size(s.indexrelid) DESC LIMIT $TOP"

echo
echo "## Razón de aciertos de caché (shared_buffers)"
psql_informe -c "SELECT 'tablas' AS tipo, sum(heap_blks_hit) AS aciertos, sum(heap_blks_read) AS lecturas_disco,
        round(100.0 * sum(heap_blks_hit) / NULLIF(sum(heap_blks_hit) + sum(heap_blks_read), 0), 2) AS pct_aciertos FROM pg_statio_user_tables
    UNION ALL
    SELECT 'índices', sum(idx_blks_hit), sum(idx_blks_read),
        round(100.0 * sum(idx_blks_hit) / NULLIF(sum(idx_blks_hit) + sum(idx_blks_read), 0), 2) FROM pg_statio_user_indexes
    UNION ALL
    SELECT 'base (pg_stat_database)', blks_hit, blks_read, round(100.0 * blks_hit / NULLIF(blks_hit + blks_read, 0), 2)
        FROM pg_stat_database WHERE datname = current_database()"

echo
echo "## Tiempo de E/S de la base (necesita track_io_timing=on), en ms"
psql_informe -c "SELECT blk_read_time AS lectura_ms, blk_write_time AS escritura_ms, xact_commit AS confirmadas, xact_rollback AS deshechas,
        temp_files AS archivos_temporales, pg_size_pretty(temp_bytes) AS bytes_temporales, deadlocks
    FROM pg_stat_database WHERE datname = current_database()"

echo
echo "## Tamaño de las $TOP tablas mayores (tabla, índices, total)"
psql_informe -c "SELECT relname AS tabla, n_live_tup AS filas, pg_size_pretty(pg_table_size(relid)) AS tabla_mb,
        pg_size_pretty(pg_indexes_size(relid)) AS indices, pg_size_pretty(pg_total_relation_size(relid)) AS total
    FROM pg_stat_user_tables ORDER BY pg_total_relation_size(relid) DESC LIMIT $TOP"

echo
echo "## Conexiones"
psql_informe -c "SELECT state, count(*) FROM pg_stat_activity WHERE datname = current_database() GROUP BY state ORDER BY 2 DESC"
