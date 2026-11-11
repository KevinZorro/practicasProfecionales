# Plan de pruebas de rendimiento

| | |
|---|---|
| Identificador | PPR-01 |
| Versión y fecha | 1.0, 2026-11-11 |
| Estructura | ISO/IEC/IEEE 29119-3:2021, anexo E (plan de pruebas) |
| Modelo de calidad | ISO/IEC 25010:2023, característica *eficiencia de desempeño* |
| Medidas | ISO/IEC 25023:2016, medidas de eficiencia de desempeño (PTb, PRu, PCa) |
| Tipos de prueba | Los del programa ISTQB de pruebas de rendimiento (CT-PT): humo, carga, estrés, pico, resistencia, concurrencia, capacidad y escalabilidad |
| Arnés | `rendimiento/` (`rendimiento/README.md`), decisión en el ADR 0008 |
| Informe de la primera ejecución | [`informe-2026-11-11.md`](informe-2026-11-11.md) |

## 1. Contexto

### 1.1 Proyecto

La plataforma reemplaza procesos manuales del laboratorio para unas 850 personas (RNF01) y se desplegará en el servidor institucional con `docker-compose.produccion.yml`. Antes de este plan, los escenarios de rendimiento de `docs/escenarios-de-calidad.md` estaban **sin medir**, y la auditoría de arquitectura dejó la prueba de carga como pendiente (B6.3).

### 1.2 Elementos de prueba

| Elemento | Qué se prueba de él |
|---|---|
| Aplicación (Laravel 12, Livewire 3) en PHP-FPM 8.3 | Tiempo de respuesta de las pantallas del panel, del JSON del calendario, de las acciones de Livewire y de las descargas |
| nginx de producción | Tiempo total por petición (`$request_time`), compresión, topes por IP |
| PostgreSQL 16 | Consultas calientes, planes de ejecución, capacidad de la máquina (TPC-B) |
| Lo que descarga el navegador | Bytes de la primera carga, Core Web Vitals, recorridos de punta a punta |

### 1.3 Alcance

**Dentro:** comportamiento temporal, utilización de recursos y capacidad del servidor (aplicación, nginx y base de datos) y del lado del cliente, con el volumen de datos real de dos semestres más el vigente (retención P13) y con cinco veces ese volumen.

**Fuera, con su razón:**

- **La entrada con Google** (RF18): es un servicio externo con su propio tiempo máximo (B3.1, ya probado en la suite). Las pruebas entran con sesiones ya creadas (ADR 0008).
- **El correo** (SMTP de Google): sale por la cola; se mide la cola, no el proveedor.
- **El TLS y la red de la universidad**: los termina el proxy institucional (ADR 0006). La red lenta se simula en el navegador.
- **El panel del ADMIN en Filament** (`/admin`): lo usa una sola persona; queda en la prueba de humo.

### 1.4 Supuestos y restricciones

- **Las cifras dependen de la máquina.** La primera ejecución se hizo en un entorno de pruebas (4 vCPU, 16 GB) con el generador de carga en la misma máquina; el informe lo dice y las cifras se deben repetir en el servidor institucional antes de entrar en producción.
- **Los datos son sintéticos**, con la población y el volumen reales (`rendimiento/datos/generar-volumen.php`), nunca datos personales.
- **Todo el tráfico de prueba sale de una sola IP.** Eso es exactamente lo que vería nginx detrás del proxy de la universidad sin `PROXIES_DE_CONFIANZA` (B3.8). Por eso las pruebas se corren en dos modos: «una sola IP» (producción hoy) e «IP real por usuario» (producción con el ADR 0006 completo), que es el que mide la capacidad de la aplicación.

### 1.5 Partes interesadas

| Parte | Qué necesita saber |
|---|---|
| Coordinación del laboratorio | Si la plataforma aguanta el inicio de clase y el uso desde el celular |
| Administrador del servidor | Cómo repetir las pruebas, qué vigilar y qué ajustar |
| Desarrollador | Qué pantalla, consulta o configuración corregir primero |

## 2. Comunicación

Los resultados de cada campaña se publican en `docs/rendimiento/` como informe de finalización (ISO/IEC/IEEE 29119-3, anexo G), con sus evidencias en `docs/rendimiento/evidencias/<fecha>/`. Lo que cambie un escenario de calidad se refleja en `docs/escenarios-de-calidad.md` y en la matriz de trazabilidad.

## 3. Registro de riesgos

| # | Riesgo de producto | Probabilidad / impacto | Prueba que lo cubre |
|---|---|---|---|
| R1 | Al inicio de clase cientos de personas entran a la vez detrás de una sola IP y nginx responde 429 (B3.8) | Alta / alto | Pico en los dos modos de IP |
| R2 | Los 20 procesos de PHP-FPM se saturan y las peticiones esperan en cola o fallan con 502/504 | Media / alto | Estrés y capacidad |
| R3 | Una consulta crece con el volumen y degrada al llegar el segundo semestre de datos | Media / medio | Planes de ejecución ×1 y ×5, escalabilidad |
| R4 | Un N+1 nuevo multiplica las consultas de una pantalla | Media / medio | Presupuesto de consultas en la suite (CI) |
| R5 | El panel pesa demasiado para un celular en 3G (RNF10) | Media / medio | Navegador, perfil 3G con CPU 4× |
| R6 | La plataforma se degrada con horas de uso (sesiones y caché en PostgreSQL, memoria de PHP) | Baja / medio | Resistencia |
| R7 | Escrituras simultáneas sobre lo mismo (aprobar, asignar sala) esperan demasiado en los bloqueos | Baja / medio | Concurrencia |
| R8 | Un reporte PDF o Excel acapara el servidor | Media / medio | Descargas y sus topes |

**Riesgos del proyecto:** el entorno de pruebas no es el servidor institucional (mitigación: el arnés se corre igual allí); el generador comparte CPU con el sistema (mitigación: en la campaña se reparten los núcleos entre los dos y se vigila la CPU del generador).

## 4. Estrategia

### 4.1 Tipos de prueba

| Tipo (ISTQB) | Pregunta que responde | Carga | Archivo | Medidas ISO/IEC 25023 |
|---|---|---|---|---|
| Humo / línea base | ¿Cuánto tarda cada pantalla sin competencia? | 1 usuario recorre todas las pantallas de todos los roles | `k6/humo.js` | PTb-1-G |
| Carga (RNF01) | ¿Se cumple el escenario tal como está escrito? | 150 personas abren el panel y el calendario en 5 min | `k6/carga-rnf01.js` | PTb-1-G, PTb-2-G |
| Carga de hora pico | ¿Y con la mezcla real de roles? | 300 usuarios con tiempos de reflexión, 10 min | `k6/carga-mixta.js` | PTb-1-G, PTb-5-G, PRu-1-G, PRu-2-G |
| Pico | ¿Qué pasa al empezar la clase? | De 0 a 300 usuarios en 10 s | `k6/pico.js` | PTb-1-G, errores 429/5xx |
| Estrés | ¿Dónde y cómo se rompe? | Tasa creciente hasta degradar | `k6/estres.js` | PTb-1-G, tipo de fallo |
| Capacidad | ¿Cuánto aguanta cumpliendo el objetivo? | Escalones de tasa constante | `k6/capacidad.js` | PCa-1-G, PCa-2-G |
| Resistencia | ¿Se degrada con el tiempo? | Carga moderada constante, 30 min | `k6/resistencia.js` | PTb-1-G por ventana, PRu-2-G |
| Concurrencia | ¿Cuánto esperan las escrituras simultáneas? | N personas hacen la misma escritura a la vez | `k6/concurrencia.js` | PTb-1-G bajo contención |
| Operaciones pesadas | ¿Cuánto cuesta un reporte y funcionan los topes? | Descargas PDF y Excel | `k6/descargas.js` | PTb-1-G, topes 429 |
| Escalabilidad por volumen | ¿Cómo cambia todo con cinco veces los datos? | Humo y carga con ×1 y ×5 | `datos/generar-volumen.php` | Cociente ×5 / ×1 |
| Base de datos | ¿Cuánto da la máquina y cuánto cuestan las consultas calientes? | pgbench TPC-B y consultas reales | `base-de-datos/` | PTb-1-G, PTb-5-G (TPS) |
| Navegador | ¿Qué vive la persona en escritorio y en un celular 3G? | Una persona, caché vacía y llena | `navegador/web-vitals.js` | PTb-1-G, PRu-4-S (bytes) |
| Punta a punta | ¿Cuánto tarda una tarea completa? | Un recorrido por rol | `navegador/recorridos.js` | PTb-3-G |
| Regresión de consultas | ¿Apareció un N+1? | Una petición con n y con 4n registros | `tests/Feature/Rendimiento/` | Consultas por petición |

Las rutas de la tabla son relativas a `rendimiento/`, salvo la última.

### 4.2 Diseño de la carga

- **Perfil operacional por rol.** Cada usuario virtual es una persona con su rol activo y ve lo que el menú le da a ese rol: estudiantes consultan resultados y su formato; docentes, sus solicitudes, el calendario y las evaluaciones; administrativos, el tablero del día, el inventario y la bandeja; coordinación, la bandeja, los reportes y la bitácora.
- **Tiempos de reflexión** de 3 a 10 s entre pantallas en las cargas realistas; sin ellos en estrés y capacidad, donde lo que se controla es la tasa de llegada.
- **Caché del navegador vacía** la primera vez: la carga del RNF01 pide también los assets, como lo haría un navegador.
- **Cada petición lleva el nombre estable de su pantalla**, para que los percentiles se agrupen por pantalla y no por URL.

### 4.3 Criterios de aceptación

| Criterio | Umbral | Origen |
|---|---|---|
| RNF01 | p95 ≤ 2 s en las páginas, medido en nginx; 0 respuestas 429 y 0 5xx | `docs/escenarios-de-calidad.md` |
| Carga de hora pico | p95 ≤ 2 s; errores < 1 % | Mismo objetivo que RNF01 con la mezcla real |
| Satisfacción (Apdex) | Apdex ≥ 0,94 («excelente») con T = 0,5 s | Apdex Alliance; con T = 0,5 s el límite de frustración (4T) coincide con los 2 s del RNF01 |
| RNF10 | ≤ 300 KB comprimidos en la primera carga del panel, perfil 3G | `docs/escenarios-de-calidad.md` |
| Core Web Vitals, escritorio | LCP ≤ 2,5 s, CLS ≤ 0,1, INP ≤ 200 ms | Umbrales de «bueno» de web.dev |
| Celular 3G de gama baja | LCP ≤ 4 s | Límite de «necesita mejorar» de web.dev |
| Capacidad | La tasa más alta con p95 ≤ 2 s y 0 errores; se informa, no se exige | ISO/IEC 25023, PCa-1-G |
| Resistencia | p95 de la última ventana ≤ 1,2 × el de la primera; 0 errores | Deriva aceptable fijada en este plan |
| Escalabilidad | p95 con ×5 ≤ 2 s en todas las pantallas | El mismo objetivo con cinco años de crecimiento |
| Consultas | Igual número de consultas con n y con 4n registros | CLAUDE.md §6, sin N+1 |

### 4.4 Medidas que se recogen

| Medida (ISO/IEC 25023) | Cómo se obtiene |
|---|---|
| PTb-1-G Tiempo medio de respuesta | k6 (media y percentiles p50/p90/p95/p99 por pantalla) y nginx (`rt=$request_time`) |
| PTb-2-G Adecuación del tiempo de respuesta | p95 medido / 2 s del RNF01 |
| PTb-3-G Tiempo de ida y vuelta | Recorridos del navegador, paso a paso |
| PTb-5-G Rendimiento medio | Peticiones por segundo atendidas (k6) y transacciones por segundo (pgbench) |
| PRu-1-G y PRu-2-G Uso de procesador y memoria | `docker stats` cada 2 s por contenedor (`herramientas/muestrear-recursos.sh`) |
| PRu-4-S Uso de ancho de banda | Bytes transferidos en la primera carga (navegador) |
| PCa-1-G Capacidad de transacciones | Último escalón de `capacidad.js` que cumple el objetivo |
| PCa-2-G Capacidad de acceso de usuarios | Usuarios simultáneos sostenidos en carga y pico sin errores |

Además, en PostgreSQL: las consultas que más tiempo suman (`pg_stat_statements`), las tablas con lecturas secuenciales y la razón de aciertos de caché (`base-de-datos/estadisticas.sh`).

### 4.5 Datos de prueba

Base desechable, migrada desde cero, con `rendimiento/datos/generar-volumen.php`: unas 1.000 cuentas de estudiantes (700 activas), 150 docentes, el personal del laboratorio, tres periodos académicos y unas 4.500 solicitudes con sus estudiantes, preparaciones, evaluaciones, formatos y bitácora. Con `RENDIMIENTO_FACTOR=5`, cinco veces lo que crece con el tiempo y la población. El generador se niega a correr sobre una base con más de 50 usuarios.

### 4.6 Entorno de prueba

El de producción (`docker-compose.produccion.yml`) más `rendimiento/docker-compose.rendimiento.yml`, que solo agrega lo que hace falta para medir: estadísticas de consultas en PostgreSQL, el puerto de la base en la propia máquina y la cookie de sesión sin `secure` (el entorno de pruebas va por HTTP). Las versiones de las herramientas están fijadas (`grafana/k6:2.3.0`, `postgres:16`).

### 4.7 Repetición y regresión

- **En cada PR**, la suite corre el presupuesto de consultas.
- **Antes de entrar en producción**, la campaña completa en el servidor institucional, con su propio informe.
- **Después de cada cambio grande** (un módulo nuevo, un cambio de esquema en una tabla grande, otra versión de PHP o PostgreSQL), la prueba de humo y la carga del RNF01, comparadas con el último informe.

### 4.8 Suspensión y reanudación

Una prueba se suspende y se repite, sin contar su resultado, si el generador pasa del 90 % de su CPU (mediría al generador y no al sistema), si falla por el entorno (disco lleno, un contenedor reiniciado por otra causa) o si los datos no son los del plan. Se reanuda desde el paso suspendido, con la base regenerada si la prueba anterior escribió en ella.

## 5. Actividades

1. Preparar el entorno y los datos (×1).
2. Línea base de la base de datos y de cada pantalla (humo).
3. Carga del RNF01, carga de hora pico, pico (en los dos modos de IP), estrés, capacidad, resistencia, concurrencia y descargas, cada una con su muestreo de recursos, su ventana del log de nginx y sus estadísticas de PostgreSQL.
4. Navegador y recorridos.
5. Escalabilidad: regenerar con ×5 y repetir la línea base, la carga del RNF01 y los planes de ejecución.
6. Informe de finalización.

Las pruebas se corren **en serie**: dos a la vez se contaminan las medidas.

## 6. Responsables

Un solo desarrollador (proyecto de práctica): diseña, ejecuta e informa. El administrador del servidor repite la campaña con `rendimiento/README.md` y la coordinación del laboratorio recibe el informe.
