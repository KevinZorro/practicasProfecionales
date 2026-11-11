# Pruebas de base de datos

Herramientas para medir PostgreSQL con el volumen de producción. Mide la
base, no la aplicación: sin PHP, sin nginx, sin red. Lo que sale es el piso
del tiempo de respuesta. Todo corre con el contenedor `postgres:16` (la misma
versión que producción) y se conecta con las variables `PGHOST`, `PGPORT`,
`PGUSER`, `PGPASSWORD` y `PGDATABASE`; con `USAR_LOCAL=si` usa los `psql` y
`pgbench` del equipo. Los resultados quedan en
`rendimiento/resultados/base-de-datos/` (fuera de Git).

## Qué mide cada script

| Script | Tipo de prueba (ISTQB CT-PT) | Medida ISO/IEC 25023 |
|---|---|---|
| `../datos/generar-volumen.php` | preparación de datos (no mide) | — |
| `referencia-tpcb.sh` | humo / línea base del hardware | PTb-5-G rendimiento medio (TPS), PTb-1-G tiempo medio de respuesta, PCa-1-G capacidad de procesamiento de transacciones |
| `capturar-consultas.php` | preparación (no mide) | — |
| `explicar.sh` | análisis por consulta (apoya a la carga) | PTb-1-G tiempo de respuesta de cada consulta |
| `consultas-calientes.sh` | carga (cada consulta, 1, 10 y 20 clientes) y concurrencia (la mezcla ponderada) | PTb-1-G / PTb-2-G (media y p50, p95, p99), PTb-5-G (TPS), PCa-1-G |
| `estadisticas.sh` | utilización de recursos | PRu-3-G uso medio de E/S (aciertos de caché, lecturas a disco), tablas e índices |

20 clientes es `pm.max_children` de PHP-FPM: el máximo de conexiones que la
aplicación puede tener abiertas a la vez.

## Orden de trabajo

```bash
# 1. Base nueva y migrada, y su volumen (x1 en ~7 s; x5 en ~25 s).
DB_HOST=127.0.0.1 DB_DATABASE=<base> php artisan migrate --force
RENDIMIENTO_BASE_DESECHABLE=si RENDIMIENTO_FACTOR=1 DB_HOST=127.0.0.1 DB_DATABASE=<base> \
  php rendimiento/datos/generar-volumen.php

# 2. Capturar el SQL real de la aplicación sobre esa base (una vez por volumen).
DB_HOST=127.0.0.1 DB_DATABASE=<base> php rendimiento/base-de-datos/capturar-consultas.php
#   x5: RENDIMIENTO_SALIDA=$PWD/rendimiento/base-de-datos/consultas-x5 ...

# 3. Medir.
export PGHOST=127.0.0.1 PGPORT=5432 PGUSER=laboratorio PGPASSWORD=secret PGDATABASE=<base>
./rendimiento/base-de-datos/referencia-tpcb.sh        # crea y borra su propia base pgbench_referencia
./rendimiento/base-de-datos/explicar.sh               # planes y resumen de problemas
DURACION=60 ./rendimiento/base-de-datos/consultas-calientes.sh
```

Variables de cada script, en su cabecera. Las más usadas: `DURACION`,
`CLIENTES`, `ESCENARIOS`, `MUESTRAS`, y `CONSULTAS=<carpeta>` para medir la
base x5 con `consultas-x5/` (los rangos de ids de los `\set` son los del
volumen sobre el que se capturó).

## Contra el SUT de producción

El SUT usa `docker-compose.produccion.yml`; este override (`../docker-compose.rendimiento.yml`)
activa `pg_stat_statements`, `track_io_timing` y el registro de consultas de
más de 250 ms, publica la base en `127.0.0.1:55432` y baja
`SESSION_SECURE_COOKIE` porque el SUT va por http. En producción real no se usa.

```bash
docker compose -f docker-compose.produccion.yml -f rendimiento/docker-compose.rendimiento.yml up -d

# Datos de volumen con el usuario de la aplicación (solo filas), por stdin:
docker compose -f docker-compose.produccion.yml -f rendimiento/docker-compose.rendimiento.yml \
  exec -T -e RENDIMIENTO_BASE_DESECHABLE=si app php < rendimiento/datos/generar-volumen.php

# Pruebas de base: el puerto publicado y el usuario de la aplicación.
export PGHOST=127.0.0.1 PGPORT=55432 PGUSER=<DB_USERNAME> PGPASSWORD=<DB_PASSWORD> PGDATABASE=<DB_DATABASE>
./rendimiento/base-de-datos/explicar.sh
./rendimiento/base-de-datos/consultas-calientes.sh

# Estadísticas: pg_stat_statements se crea con el dueño (DB_DUENO_USUARIO).
PGUSER=<DB_DUENO_USUARIO> PGPASSWORD=<DB_DUENO_CONTRASENA> ./rendimiento/base-de-datos/estadisticas.sh reiniciar
# ... prueba de carga ...
PGUSER=<DB_DUENO_USUARIO> PGPASSWORD=<DB_DUENO_CONTRASENA> ./rendimiento/base-de-datos/estadisticas.sh leer > informe.txt
```

`consultas-calientes.sh` crea y borra mil filas de `sessions` con id `rend-N`;
no toca otra cosa. `explicar.sh` corre en transacciones que se deshacen.
`ESTADISTICAS=si` en `consultas-calientes.sh` reinicia `pg_stat_statements`
antes y guarda su lectura al final (necesita el usuario dueño).

## Limitaciones a tener presentes al leer las cifras

- Las consultas de carga anticipada (`with()`) llevan los ids de la captura, fijos.
- Las fechas van relativas a `CURRENT_DATE`: si el volumen se generó otro día, la ventana se desplaza.
- `pg_stat_statements` y `track_io_timing` cuestan algo de CPU: las latencias con el override salen un poco por encima.
- Con factor 5 las 8 salas no alcanzan para tantas sesiones: unas pocas aprobadas próximas quedan sin sala.
