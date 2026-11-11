# Rendimiento de punta a punta en el navegador

Pruebas con **k6 browser** (Chromium real, imagen `grafana/k6:2.3.0-with-browser`).
Miden lo que vive la persona delante de la pantalla, no lo que tarda el servidor
(eso lo miden las pruebas de carga HTTP).

| Script | Qué mide | Medida |
|---|---|---|
| `web-vitals.js` | Por página y perfil, en carga fría y en caliente: TTFB, FCP, LCP, CLS, INP, bytes transferidos y número de peticiones | ISO/IEC 25023 PTb-1-G (tiempo de respuesta: TTFB, FCP, LCP), PTb-2-G (medido / objetivo, en los umbrales), PRu-4-S (ancho de banda: bytes); W3C Navigation, Resource, Paint, LCP, Layout Instability y Event Timing |
| `recorridos.js` | Cinco recorridos reales, paso a paso, del gesto a la interfaz actualizada | ISO/IEC 25023 PTb-3-G (tiempo de ida y vuelta de una tarea) |
| `comun.js` | Perfiles, sesiones, lectura de métricas dentro de la página | (no se ejecuta solo) |

Escenarios de calidad que alimenta: RNF10 (primera carga del panel en celular 3G,
≤ 300 KB comprimidos) y, de forma indirecta, RNF01 (p95 ≤ 2 s) en los pasos de cada recorrido.

## Requisitos

1. Docker y la imagen `grafana/k6:2.3.0-with-browser` (trae Chromium; no se instala nada más).
2. Las sesiones forjadas, solo contra una base desechable (la entrada real es Google):

   ```bash
   DB_HOST=127.0.0.1 RENDIMIENTO_BASE_DESECHABLE=si RENDIMIENTO_SESIONES_POR_ROL=3 \
     php rendimiento/herramientas/crear-sesiones.php > rendimiento/resultados/sesiones-navegador.json
   ```

   Hacen falta sesiones de `estudiante`, `docente`, `administrativo` y `coordinador`.
   Sin el archivo, `web-vitals.js` mide solo las páginas públicas y `recorridos.js` no corre.
3. Una carpeta de salida que exista (k6 no crea carpetas): `mkdir -p rendimiento/resultados/navegador`.

## Comando

Desde la raíz del repositorio. El directorio de trabajo del contenedor tiene que ser
`rendimiento/navegador` (las rutas por defecto de `SESIONES` y `SALIDA` son relativas a él):

```bash
docker run --rm --network host --shm-size=512m \
  --user "$(id -u):$(id -g)" -e HOME=/tmp -e K6_BROWSER_ARGS=no-sandbox \
  -e BASE_URL=http://localhost \
  -v "$PWD":/repo -w /repo/rendimiento/navegador \
  grafana/k6:2.3.0-with-browser run --quiet web-vitals.js
```

Cambia `web-vitals.js` por `recorridos.js` para los recorridos. Qué hace cada opción:

- `--network host`: alcanza `localhost` / `127.0.0.1` del equipo.
- `--user …` y `-e HOME=/tmp`: los resultados quedan con tu dueño y Chromium tiene dónde escribir su perfil.
- `K6_BROWSER_ARGS=no-sandbox`: Chromium no puede abrir su sandbox dentro de un contenedor sin
  `--cap-add=SYS_ADMIN`; con este argumento no hace falta dar ninguna capacidad.
- `--shm-size=512m`: Chromium usa `/dev/shm` (64 MB por defecto) y las páginas pesadas, como el
  calendario, se caen si se queda corto. Es una precaución; en la validación se corrió también
  como `root` sin `--shm-size` ni `no-sandbox` y funcionó.
- Nada de `--cap-add` ni `--privileged`.

## Variables

Comunes: `BASE_URL` (`http://localhost`), `SESIONES` (`../resultados/sesiones-navegador.json`),
`PERFILES` (`escritorio,movil3g`), `ITERACIONES` (1), `SALIDA` (`../resultados/navegador`).

`web-vitals.js`: `PAGINAS` (ids separados por coma: `portada, escenario, hoy-estudiante, hoy-docente,
hoy-administrativo, calendario, bandeja, tablero`; todas por defecto), `ESCENARIO_ID` (si falta se
busca en la portada y, si no hay escenario publicado, `escenario` se omite con un aviso),
`FECHA_TABLERO` (AAAA-MM-DD del tablero de preparaciones; hoy por defecto, y ese día debe tener
sesiones aprobadas para que el tablero tenga tarjetas), `SOLO_FRIA=1` (omite la visita en caliente),
`REPOSO_MS` (1000: espera tras la carga antes de leer).

`recorridos.js`: `ESTUDIANTES` (2, los que el docente agrega al grupo), `BUSQUEDA` (`a`, texto con el
que busca estudiantes), `MATERIA_ID` y `CASO_ID` (fijar materia y escenario).

**`recorridos.js` crea datos** (una solicitud, aprobada y con sala, por iteración). Solo contra bases desechables.

## Perfiles

Los define `comun.js` y cada uno se corre en un contexto nuevo (caché y cookies vacías).

| Perfil | Definición | Fuente |
|---|---|---|
| `escritorio` | 1366×768, sin limitar red ni CPU | Resolución de portátil habitual; la referencia sin limitación |
| `movil3g` | Red: 1,6 Mbps de bajada, 768 Kbps de subida, 300 ms de RTT. CPU 4× más lenta (`page.throttleCPU({ rate: 4 })`). Pantalla 412×823 a 1,75, táctil, agente de Chrome móvil | Red: perfil «3G» de WebPageTest. CPU: `cpuSlowdownMultiplier` de Lighthouse en móvil (referencia: un Moto G4). Pantalla: emulación móvil de Lighthouse (Moto G Power) |

La limitación de red es de Chromium (CDP `Network.emulateNetworkConditions`) y alcanza también a
`localhost`: los 300 ms de RTT se pagan aunque el servidor esté en la misma máquina.

## Qué se hace en cada página (`web-vitals.js`)

1. Contexto nuevo con el perfil y, si la página es del panel, la cookie de sesión del rol (`context.addCookies`).
2. **Visita fría:** `goto` esperando red quieta, 1 s de reposo y lectura de las métricas.
3. **Interacción para el INP:** se pulsa (o toca, en móvil) el primer control visible que no navegue
   (menú del celular, «Siguiente» del calendario, la pastilla «Pendientes» de la bandeja, «Hoy» del
   tablero; la portada y el detalle del escenario caen al `main`) y se lee la duración.
4. **Visita en caliente:** otro `goto` a la misma URL en el mismo contexto (conserva la caché HTTP).

Los observadores (`PerformanceObserver`) se instalan con `addInitScript` antes de que cargue nada, porque
el búfer del navegador solo guarda los eventos de 104 ms o más.

| Métrica | Cómo sale |
|---|---|
| TTFB | `responseStart` de Navigation Timing |
| FCP | entrada `first-contentful-paint` de Paint Timing |
| LCP | último `largest-contentful-paint` (se registra además el elemento que fue) |
| CLS | ventana de sesión más mala (huecos de 1 s, tope de 5 s), de `layout-shift` sin entrada reciente; solo la carga, no la interacción |
| INP | la interacción más lenta de Event Timing (con menos de 50 interacciones, así lo define web.dev). Event Timing no entrega eventos de menos de 16 ms: si no hay entrada se registra **16 y se marca `inpEsCota`** (es una cota superior, no una medición) |
| Bytes transferidos | suma de `transferSize` (cuerpo comprimido más cabeceras, lo que viajó por la red) de Navigation y Resource Timing; 0 si salió de la caché |
| Cuerpos comprimidos / descomprimidos | suma de `encodedBodySize` / `decodedBodySize` |
| Peticiones | entradas de Navigation y Resource Timing; `peticiones_en_cache` son las que no viajaron |

**Sobre el contador de k6.** `browser_data_received` de k6 **no sirve** para el RNF10: cuenta el cuerpo ya
descomprimido más las cabeceras. En la portada del SUT con nginx comprimiendo, k6 contó unos 129.800 B por
visita (126.339 de cuerpos descomprimidos más cabeceras) y la red movió 76.074 B (`transferSize`). El resumen imprime ambas cifras como comprobación cruzada
(`SOLO_FRIA=1` la deja limpia). La cifra de la prueba es `transferSize`.

## Umbrales

| Qué | Valor | Fuente / justificación |
|---|---|---|
| Bytes de la primera carga de cada página del panel | ≤ 307.200 (300 KB) | RNF10, `docs/escenarios-de-calidad.md`: «≤ 300 KB comprimidos en la primera carga». Se evalúa con el máximo de la visita fría, en los dos perfiles |
| LCP, escritorio | ≤ 2,5 s (p75) | Core Web Vitals «bueno» (web.dev) |
| CLS, escritorio | ≤ 0,1 (p75) | Core Web Vitals «bueno» |
| INP, escritorio | ≤ 200 ms (p75) | Core Web Vitals «bueno» |
| FCP, escritorio | ≤ 1,8 s (p75) | «Bueno» de web.dev; referencia |
| TTFB, escritorio | ≤ 0,8 s (p75) | «Bueno» de web.dev; referencia |
| LCP, 3G | ≤ 4 s (p75) | Por encima de 4 s web.dev lo llama «malo»; el RNF10 pide «pantalla usable», no 2,5 s, en 3G |
| Paso de un recorrido, escritorio | p95 ≤ 2 s | Mismo objetivo del RNF01 (p95 ≤ 2 s); es referencia, no hay objetivo acordado por paso |

En 3G solo se exigen el LCP y los bytes; el resto se registra sin umbral. Los Core Web Vitals se definen sobre
el percentil 75 de usuarios reales; con pocas iteraciones, `p(75)` es casi el máximo, así que una campaña
real debe usar varias (`ITERACIONES`). Los umbrales que fallan terminan k6 con código 99: es el resultado
esperado de una prueba que no se cumple, no una avería del arnés.

## Qué recorre `recorridos.js`

Cada actor tiene su propio contexto y su sesión; el recorrido a-d es una cadena (la solicitud de la
a es la que aceptan, aprueban y a la que asignan sala).

| Recorrido | Actor | Pasos cronometrados | Componente |
|---|---|---|---|
| `a-solicitar` | docente | `abrir_formulario`, `elegir_materia`, `elegir_caso`, `agregar_estudiante` (cada uno), `enviar_solicitud` | `FormularioSolicitud` |
| `b-revisar` | administrativo | `abrir_expediente`, `marcar_revisada` | `ExpedienteDeSolicitud::revisar` |
| `c-aprobar` | coordinador | `abrir_expediente`, `aprobar` | `ExpedienteDeSolicitud::aprobar` |
| `d-asignar-sala` | administrativo | `abrir_tablero`, `abrir_panel_de_montaje`, `asignar_sala` | `TableroDiario::asignarSalas` |
| `e-resultados` | estudiante | `abrir_mis_resultados` | `MisResultados` |

Cada recorrido suma además un paso `total` (suma de sus pasos cronometrados). El paso va del gesto hasta una
condición visible en la interfaz (aparece la lista de escenarios, el aviso de éxito, la pantalla de destino),
medida con `waitForFunction` en cada fotograma.

Versión reducida, a propósito: **no se cronometra teclear** la fecha, la hora, el grupo ni marcar la casilla de
la sala (se llenan con `fill()`/`check()`; no viajan al servidor hasta el envío). `agregar_estudiante` incluye
los 400 ms de espera (debounce) que el formulario aplica a la búsqueda: es tiempo que la persona percibe.
La fecha de la solicitud se elige al azar entre 20 y 400 días adelante para no cruzarse con otras; si el sistema
avisa de un cruce se reintenta hasta tres veces y solo se cronometra el envío que sirvió. Para la sala se prueba
una a una hasta que el sistema acepta alguna.

## Resultados

En `SALIDA` (por defecto `rendimiento/resultados/navegador/`, ignorada por git):

- `web-vitals.js`: `<pagina>__<perfil>.json` y `.txt` (por visita fría y caliente: mediana, p75, mínimo,
  máximo, n y veredicto de cada umbral), más `resumen.json` y `resumen.txt` con la tabla consolidada.
- `recorridos.js`: `recorridos.json` y `recorridos.txt` (por recorrido, perfil y paso).
- En el registro de k6 (stderr) sale una línea `MEDICION {…}` por cada visita con el detalle que no cabe
  en una métrica: elemento del LCP, tamaño del documento y los **seis recursos más pesados** con su
  `transferSize`. Guárdalo con `2> registro.txt` si quieres localizar qué recurso engorda una página.
  `recorridos.js` deja líneas `PASO …` y `TOTAL …`.

Cómo leerlos: la **mediana de la visita fría** es la cifra de la página; la visita en caliente dice cuánto
ayuda la caché (assets de `/build` con caché de un año: en el SUT bajó la portada de 74 KB a 2,4 KB). Una
página cumple el RNF10 si su `Bytes transferidos` de la visita fría es ≤ 307.200 **y** su LCP en `movil3g`
es ≤ 4 s. Un INP marcado `inpEsCota` significa «no más de 16 ms».

## Limitaciones

- Los bytes y tiempos solo valen contra el SUT de producción (nginx comprimiendo, Livewire minificado,
  `APP_DEBUG` apagado). `php artisan serve` no comprime y sirve `livewire.js` sin minificar (391 KB contra
  57 KB comprimidos del `livewire.min.js` del SUT): sirve para validar los scripts, no para medir.
- El INP mide la respuesta visual del gesto, no el viaje de ida y vuelta de Livewire; ese lo cubren los pasos
  de `recorridos.js`.
- La visita en caliente reutiliza la caché del contexto, no la de otra sesión de navegador.
- `escenario` depende de que haya un escenario publicado.
- Dos ejecuciones a la vez compiten por CPU y falsean los tiempos: una cada vez.
- El tiempo de cada paso se toma en k6 (`Date.now()`) y suma unos milisegundos de comunicación con Chromium.
