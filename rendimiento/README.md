# Arnés de pruebas de rendimiento

Todo lo de esta carpeta corre **fuera de la suite Pest y fuera de la imagen de
producción** (`.dockerignore` excluye `rendimiento/`). No añade dependencias a
`composer.json` ni a `package.json`: k6 se usa por contenedor con versión fija
(`grafana/k6:2.3.0`). Las salidas van a `rendimiento/resultados/<categoría>/`
(carpeta fuera de Git).

Marco: ISO/IEC 25010:2023 (eficiencia de desempeño), ISO/IEC 25023:2016 (medidas),
tipos de prueba de ISTQB CT-PT. Percentiles p50/p90/p95/p99; Apdex con T = 0,5 s
(satisfecho ≤ 0,5 s, tolerable ≤ 2 s, frustrado > 2 s: el límite de frustración
coincide con el p95 del RNF01). El plan y el informe formales (ISO/IEC/IEEE 29119-3)
se escriben aparte; estas herramientas producen los datos.

## Qué hay

| Carpeta o archivo | Para qué |
|---|---|
| `k6/` | Pruebas HTTP (este README) |
| `herramientas/crear-sesiones.php` | Sesiones autenticadas forjadas (la entrada real es solo Google) |
| `herramientas/resumir-nginx.php` | Veredicto oficial del RNF01 desde el log de nginx (`$request_time`) |
| `herramientas/muestrear-recursos.sh` | CPU, memoria y E/S por contenedor durante una prueba |
| `docker-compose.rendimiento.yml` | Override del SUT: `pg_stat_statements`, puerto 127.0.0.1:55432, cookie sin `secure` |
| `datos/generar-volumen.php` | Base con el volumen de producción |
| [`base-de-datos/README.md`](base-de-datos/README.md) | Pruebas de PostgreSQL (consultas, planes, pgbench) |
| [`navegador/README.md`](navegador/README.md) | Web Vitals y RNF10 con navegador real |

## Pruebas k6

| Archivo | Tipo (ISTQB) | Medida ISO/IEC 25023 | Umbral (pasa/no pasa) y de dónde sale |
|---|---|---|---|
| `k6/humo.js` | Humo / línea base | PTb-1-G tiempo medio de respuesta | 0 errores, 0 sesiones inválidas. Sin objetivo de latencia: es la referencia sin contención |
| `k6/carga-rnf01.js` | Carga (RNF01 tal cual) | PTb-2-G adecuación del tiempo de respuesta; PCa-2-G | p95 < 2000 ms por pantalla, 0 % de errores, 0 × 429, 0 × 5xx: `docs/escenarios-de-calidad.md`, RNF01 |
| `k6/carga-mixta.js` | Carga (hora pico) | PTb-1-G; PTb-5-G rendimiento medio | p95 < 2 s por pantalla, errores < 1 %, 0 × 5xx (el 2 s es el del RNF01; el 1 % es supuesto) |
| `k6/pico.js` | Pico | PCa-2-G; PTb-2-G | 0 × 5xx y p95 < 2 s tras el pico (recuperación); en modo IP real, 0 × 429 |
| `k6/estres.js` | Estrés | PCa-1-G; PTb-5-G | Se corta si p95 > 10 s o errores > 20 % (supuestos); el resultado es «en qué tasa degrada y qué falló primero» |
| `k6/capacidad.js` | Capacidad | PCa-1-G; PCa-2-G | La capacidad es el último escalón consecutivo con p95 ≤ 2 s y 0 errores |
| `k6/resistencia.js` | Resistencia | PRu-2-G (con el muestreo); PTb-1-G | p95 < 2 s en cada ventana, 0 errores; la deriva del p95 (primera contra última ventana) se reporta y se marca si pasa de 25 % |
| `k6/concurrencia.js` | Concurrencia de escrituras | PTb-3-G bajo contención | 0 × 5xx; a lo sumo 1 solicitud creada, a lo sumo 1 preparación con la sala, exactamente 1 aprobación (regla 9, B3.11); p95 < 2 s |
| `k6/descargas.js` | Operaciones pesadas + topes | PTb-3-G; PRu-3-G | p95 < 10 s por descarga (supuesto: el RNF no fija número); topes: 429 de nginx y 429 de la aplicación a 30 por minuto |

Los umbrales «supuesto» no vienen de un requerimiento: se pueden cambiar con variables
(`LIMITE_P95_MS`, `DESCARGA_P95_MS`, `DERIVA_MAXIMA`) y deben confirmarse en el plan.

### Cómo se corre

```bash
docker run --rm --user "$(id -u):$(id -g)" --network host \
  -v "$PWD/rendimiento:/rendimiento" -w /rendimiento \
  -e BASE_URL=http://localhost -e IP_POR_USUARIO=no \
  grafana/k6:2.3.0 run -q k6/humo.js
```

`--user` hace falta: el contenedor de k6 no es root y, sin él, no puede escribir en
`resultados/`. `-q` quita la barra de progreso; el resumen sale igual. Al terminar
quedan `resultados/k6/<escenario>-<fecha>.json` y `.txt` con p50/p90/p95/p99/máx por
pantalla, tasa de error, rps, Apdex, umbrales y **el modo de IP**. Variables comunes:

| Variable | Por defecto | Qué hace |
|---|---|---|
| `BASE_URL` | `http://localhost` | Sitio a probar |
| `SESIONES` | `/rendimiento/resultados/sesiones.json` | JSON de `crear-sesiones.php` (ruta dentro del contenedor) |
| `IP_POR_USUARIO` | `no` | Ver «Dos modos de IP» |
| `CACHE_VACIA` | (vacío) | `si`: pide los assets en cada visita (peor caso: navegador nuevo) |
| `LIMITE_P95_MS` | `2000` | Umbral de p95 de las pantallas |

Las demás (`PERSONAS`, `VUS`, `DURACION`, `ESCALONES`, `N`...) están al principio de
cada archivo, con su valor por defecto y lo que significan.

### Dos modos de IP: `IP_POR_USUARIO=no|si`

nginx de producción cuenta sus topes **por IP** (`limit_req` general de 100 r/s con
ráfaga 300, 100 conexiones, y la zona «pesadas» de 5 r/s). k6 llega al SUT desde la
pasarela de Docker, así que, sin ayuda, nginx ve **una sola IP** para todos los
usuarios virtuales.

- **`no` (una sola IP) = producción hoy**, detrás del proxy de la universidad sin
  `set_real_ip_from` (auditoría B3.8, ADR 0006 «falta la IP del proxy»). Todos los
  usuarios comparten los topes. **Los 429 que aparezcan son el hallazgo B3.8, no un
  fallo de la aplicación.** Sirve para medir qué le pasa a la universidad entera si
  nadie configura `PROXIES_DE_CONFIANZA`.
- **`si` (IP real) = producción con el ADR 0006 completo.** Cada usuario virtual manda
  `X-Forwarded-For: 10.x.y.z`, estable y distinta por usuario, en **todas** sus
  peticiones (páginas, assets, Livewire, descargas). Es el modo que **mide la capacidad
  de la aplicación**. Hace falta que el SUT confíe en la pasarela: `docker/produccion/ip-real.sh`
  solo toma esa cabecera si la petición viene de una IP de `PROXIES_DE_CONFIANZA`.
  Levanta el SUT con esa variable apuntando a la pasarela de la red del compose:

  ```bash
  docker network inspect laboratorio_laboratorio -f '{{(index .IPAM.Config 0).Gateway}}'
  # el proyecto de compose se llama «laboratorio» y su red «laboratorio»; verifícalo con
  # `docker network ls` si cambia. Pon el resultado en PROXIES_DE_CONFIANZA del .env.
  ```

Cada resumen dice `ip_por_usuario: si|no`; no mezcles resultados de los dos modos. Corre
`pico.js` y `estres.js` en **ambos** (mismo script, solo cambia la variable): el primero
dice qué pasa hoy; el segundo, qué capacidad tiene la aplicación.

## Prerrequisitos

- Docker; k6 baja solo (`grafana/k6:2.3.0`). PHP en el anfitrión para las herramientas `.php`;
  `jq` para `muestrear-recursos.sh`.
- Un SUT con datos de volumen y **una base desechable** (las pruebas de concurrencia
  escriben solicitudes; `crear-sesiones.php` forja sesiones). Nunca la base de producción.
- Sesiones suficientes: `RENDIMIENTO_SESIONES_POR_ROL` debe ser ≥ el número de personas
  de cada rol que la prueba use (la carga RNF01 necesita ~90 estudiantes y ~45 docentes
  distintos). Con menos, las sesiones se reutilizan y la prueba avisa en el registro de
  `setup()`: ya no son personas distintas.
- Para `concurrencia.js`: al menos una sesión de docente, administrativo y coordinador y
  estudiantes activos que puedan ir a una sesión.

## Orden de una campaña

1. **Levantar el SUT** con el override de rendimiento y, para el modo de IP real,
   `PROXIES_DE_CONFIANZA`:
   `docker compose -f docker-compose.produccion.yml -f rendimiento/docker-compose.rendimiento.yml up -d`
2. **Generar los datos:** `rendimiento/datos/generar-volumen.php` (ver su cabecera).
3. **Crear las sesiones** (desde la raíz del proyecto, con la base del SUT):
   `RENDIMIENTO_BASE_DESECHABLE=si RENDIMIENTO_SESIONES_POR_ROL=150 php rendimiento/herramientas/crear-sesiones.php > rendimiento/resultados/sesiones.json`
   (o dentro del contenedor `app`, por stdin: ver la cabecera del script).
4. **Muestrear recursos** durante cada prueba:
   `rendimiento/herramientas/muestrear-recursos.sh & MUESTREO=$!` … `kill $MUESTREO`; y
   `rendimiento/herramientas/muestrear-recursos.sh --resumen <archivo.jsonl>` (CPU media/p95/máx
   y memoria media/máx por contenedor: PRu-1-G y PRu-2-G).
5. **Correr cada prueba**, en serie y nunca dos a la vez (comparten IP, base y PHP-FPM), en
   este orden: `humo` → `carga-rnf01` → `carga-mixta` → `capacidad` → `pico` (los dos modos) →
   `estres` (los dos modos) → `concurrencia` → `descargas` → `resistencia` (la más larga, al final).
   Deja pasar un minuto entre pruebas para que los topes por minuto se vacíen.
6. **Resumir el log de nginx** tras cada prueba (el veredicto oficial del RNF01):
   ```bash
   docker compose -f docker-compose.produccion.yml logs --no-log-prefix --since 10m web \
     | php rendimiento/herramientas/resumir-nginx.php --solo-paginas
   ```
   Lee el formato `con_tiempos` de `docker/produccion/nginx.conf` (`rt=` y `urt=`); con
   `--json` produce JSON. El código de salida es 2 si no cumple.
7. Guardar `resultados/` junto con el modo de IP, la versión del código y el volumen de datos.

## Limitaciones

- El resumen de k6 es el punto de vista del cliente; el del RNF01 es el de nginx (paso 6).
- Los assets de `/build` se sirven con `access_log off` y no aparecen en el log de nginx.
- k6 corre en la misma máquina que el SUT: a cargas altas compiten por CPU. El estrés
  informa `dropped_iterations` cuando k6 no alcanza la tasa pedida.
- PCa-2-G (usuarios simultáneos) se **estima** con la ley de Little a partir de la tasa y de
  6,5 s de reflexión media; no se mide.
- El servidor de desarrollo (`php artisan serve`) sirve para validar los scripts, no para
  sacar cifras de rendimiento.
