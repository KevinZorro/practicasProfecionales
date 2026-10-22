# Informe de evaluación de seguridad — Plataforma del Laboratorio de Simulación Clínica

| | |
|---|---|
| **Fecha de la evaluación** | 22 de octubre de 2026 |
| **Versión evaluada** | Rama `ccr-d6664cb0-b3oyx4` del repositorio `kevinzorro/practicasprofecionales` |
| **Tipo de evaluación** | Revisión estática de código y configuración (caja blanca). No es una prueba de penetración |
| **Marcos de referencia** | OWASP Top 10:2021, OWASP ASVS 4.0 (nivel 2 como objetivo), NIST SP 800-30 para la calificación del riesgo |
| **Clasificación del documento** | Interno. Describe debilidades; no se publica fuera de la Facultad ni del equipo de mantenimiento |

---

## 1. Resumen ejecutivo

La aplicación tiene una base de seguridad **sólida para su tamaño**: no maneja contraseñas (solo acceso con Google), autoriza con Policies sobre todos los modelos operativos, usa consultas parametrizadas, sirve los documentos firmados por una ruta protegida y tiene cabeceras de seguridad y copias de seguridad que se prueban en la CI.

Los puntos que **no se pueden dejar pasar** son tres:

1. **PHP2Shell (CVE-2026-109618 y CVE-2026-109619, CVSS 9.2).** Afecta a PHP 8.3 y se corrige en 8.3.36. La imagen del proyecto parte de `php:8.3-fpm-bookworm` sin fijar el parche ni comprobarlo. Hasta reconstruir y verificar la versión, el servidor se considera vulnerable. *Crítico.*
2. **Sin límite de peticiones en ninguna capa.** La decisión de no limitar el acceso por IP (RF18) tiene sentido, pero hoy tampoco hay límite en nginx ni en las rutas del panel, las descargas o la generación de PDF. Un DDoS volumétrico no se frena desde la aplicación; hace falta protección de red. *Alto.*
3. **Datos sensibles sin protección adicional en reposo ni en tránsito hacia la base.** No hay cifrado de columnas, ni TLS configurado en la conexión a PostgreSQL, ni evidencia de cifrado de las copias de seguridad. *Alto.*

**Veredicto:** *aceptable para operar solo después de cerrar el hallazgo S-01 y de decidir los S-02 y S-03.* Nada de lo encontrado indica una brecha previa; no se hizo análisis forense.

### Estado de la remediación (actualizado el 22-oct-2026)

Las correcciones de S-01, S-02, S-03 y S-06 están **escritas y subidas, pero no verificadas**: en el entorno donde se hicieron no se pudieron instalar las dependencias de Composer (límite de descargas de GitHub), así que no se corrió la suite de tests, PHPStan ni Pint, ni se pudo probar nginx (sin Docker). Solo se comprobó la sintaxis de los archivos PHP. **Hasta que la CI los pase, ninguno de esos hallazgos está cerrado.**

| ID | Estado | Qué se hizo | Qué falta |
|---|---|---|---|
| S-01 | Corregido, sin verificar | `ARG PHP_VERSION=8.3.36` fijado en el Dockerfile; `session.upload_progress.enabled=0`; test que impide bajar de 8.3.36 | Reconstruir y desplegar en el servidor, comprobar `php -v`, y rotar secretos si hubo exposición |
| S-02 | Parcial, sin verificar | Topes de peticiones, conexiones y tiempos de espera en nginx; tope de 30 descargas/minuto por usuario; `request_terminate_timeout` en PHP-FPM | WAF o protección DDoS de la universidad; saber si hay proxy delante para que nginx vea la IP real |
| S-03 | Parcial, sin verificar | `users.documento` cifrado con migración de los datos existentes | Ver "Decisiones del cliente" |
| S-04 | Abierto | — | Test que exija Policy en toda ruta con modelo; ids públicos |
| S-05 | Riesgo aceptado | — | — |
| S-06 | Parcial, sin verificar | La sesión se cifra por defecto. El ejemplo de producción ya traía `SESSION_SECURE_COOKIE=true` | Definir `PROXIES_DE_CONFIANZA` (depende del proxy) |
| S-07 a S-10 | Abiertos | — | Ver §7 |

### Decisiones del cliente (22-oct-2026)

- **Columnas cifradas:** solo el documento de identidad. Quedan en claro las observaciones de evaluación y los motivos de bloqueo y deshabilitación.
- **Formatos de confidencialidad firmados (PDF):** sin cifrado adicional en la aplicación ni en el disco. **Riesgo aceptado por el cliente.** Quien obtenga el volumen `archivos_privados` o una copia de seguridad lee los PDF con datos personales y firmas.
- **Proxy de la universidad:** el cliente no lo sabe todavía. Pendiente.
- **Consecuencia operativa:** `APP_KEY` pasó a proteger datos; sin ella los documentos no se recuperan (ver README, "Copias de seguridad").

### Conteo de hallazgos

| Crítico | Alto | Medio | Bajo | Informativo |
|:--:|:--:|:--:|:--:|:--:|
| 1 | 2 | 4 | 2 | 1 |

---

## 2. Alcance y metodología

**Dentro del alcance:** código de la aplicación (`app/`, `routes/`, `config/`, `database/`), Dockerfile, `docker-compose.produccion.yml`, `nginx.conf`, `php-produccion.ini`, flujo de la CI (`.github/workflows/tests.yml`), dependencias de Composer y npm.

**Procedimiento:**
- Lectura de rutas, middleware, Policies, controladores de descarga y Services con consultas SQL en bruto.
- Búsqueda de patrones peligrosos: `DB::raw`/`whereRaw`, `{!! !!}`, rutas sin Policy, secretos versionados.
- `composer audit --locked` y `npm audit --omit=dev` ejecutados el 22-oct-2026.
- Contraste de la vulnerabilidad PHP2Shell con fuentes públicas (ver §6).

**Limitaciones — lo que este informe NO cubre:**
- No se hicieron pruebas dinámicas (escaneo, fuzzing, intento de explotación) ni pruebas de carga.
- No se levantó la pila de producción; no se verificó la versión real de PHP del servidor.
- No se pudo revisar `.env.produccion.example` ni los `.env` (acceso denegado a esos archivos en la sesión), por lo que **las variables de sesión, proxies y logs en producción están sin verificar** (S-06, S-07).
- No se revisó el servidor Debian, el firewall ni el proxy HTTPS de la universidad.
- No se revisó la seguridad de la cuenta de Google Workspace ni de GitHub.

---

## 3. Controles que funcionan (fortalezas)

| Área | Evidencia |
|---|---|
| Autenticación | Solo Google OAuth (RF18); no existe `users.password`. Exige `email_verified` y comprueba el dominio (`hd`) al volver. No crea cuentas; protege el `google_id` ya vinculado |
| Control de acceso | 29 Policies (una por modelo, verificadas por `PoliciesDescubiertasTest`) y más de 120 llamadas a `authorize`/`can` en controladores y componentes. El rol activo y la vigencia institucional se aplican también a las acciones de Livewire (`/livewire/update`), con tests por HTTP real |
| Roles temporales | El vencimiento se aplica en SQL, no con un job; la escritura de roles pasa por un único Service con bitácora |
| Inyección SQL | Todas las consultas en bruto usan parámetros enlazados (`whereRaw(..., [$x])`) o expresiones fijas; no se encontró concatenación de entrada de usuario |
| XSS | No hay salidas sin escapar (`{!! !!}`) en `resources/` ni en `app/` |
| Subidas de archivos | Imágenes recodificadas a WebP con tope de 1600 px y 5 MB; videos y PDF con tipo y tope validados; nombres generados por el servidor |
| Documentos privados | El formato de confidencialidad firmado vive en disco privado y se descarga con `can('descargar', …)` |
| Inyección en Excel | `ValorSeguro` impide que un texto se guarde como fórmula |
| Cabeceras | CSP, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, COOP y HSTS sobre HTTPS |
| Configuración | `display_errors=Off`, `expose_php=Off`, `server_tokens off`, procesos PHP como `www-data`, base de datos sin puerto publicado |
| Resiliencia | Copia diaria de base, imágenes y documentos privados; la CI restaura una copia dañada en cada PR |
| Cadena de suministro | `composer audit` en la CI; 0 vulnerabilidades conocidas en Composer y npm al 22-oct-2026 |
| Auditoría | Bitácora de solo añadir para aprobaciones, bloqueos, roles, formatos, periodos |
| Entradas malformadas | Parámetros de ruta numéricos (`Route::pattern`) y tests de entradas malformadas |

---

## 4. Hallazgos

Escala: **Crítico** (explotable en remoto con impacto total) · **Alto** · **Medio** · **Bajo** · **Informativo**. La calificación combina probabilidad e impacto (NIST SP 800-30).

### S-01 · PHP2Shell: ejecución remota de código en PHP 8.3 — **CRÍTICO** · Abierto
- **Categoría:** OWASP A06:2021 (componentes vulnerables y desactualizados).
- **Descripción:** CVE-2026-109618 y CVE-2026-109619 permiten ejecución remota de código sin autenticar y, el segundo, filtrar memoria del proceso (contraseñas, tokens, claves). Afecta desde PHP 5.4 hasta la rama 8.x. Corregido el 21-oct-2026 en 8.5.12, 8.4.27, **8.3.36** y 8.2.35.
- **Evidencia:** `docker/php/Dockerfile:21` usa `FROM php:8.3-fpm-bookworm`, un tag móvil. Ninguna prueba ni paso de la CI comprueba la versión de PHP resultante. `composer.json` fija solo `^8.3`.
- **Impacto:** control total del contenedor `app`, que tiene el `.env` (credenciales de la base y de Google), acceso a los documentos privados y a la base.
- **Recomendación:**
  1. Reconstruir con `docker compose -f docker-compose.produccion.yml build --pull --no-cache` y comprobar `php -v` ≥ 8.3.36 en `app`, `queue` y `programador`.
  2. Mientras tanto: `session.upload_progress.enabled=0` en `php-produccion.ini` y reiniciar php-fpm.
  3. Añadir a la CI una comprobación que falle si `PHP_VERSION` está por debajo del mínimo seguro.
  4. Suscribirse al canal de avisos de seguridad de php.net.
  5. Si el servidor estuvo expuesto con la versión vulnerable, **rotar** `APP_KEY`, la contraseña de la base, las credenciales de Google y la contraseña de aplicación del correo, y revisar los registros de acceso a partir del 17-oct-2026 (primer intento observado públicamente).

### S-02 · Sin límite de peticiones ni protección contra denegación de servicio — **ALTO** · Abierto
- **Categoría:** OWASP A04:2021 (diseño inseguro) / ASVS V11.
- **Evidencia:** no hay `throttle`, `RateLimiter` ni `limit_req`/`limit_conn` en `routes/`, `bootstrap/`, `config/` ni `docker/`. La decisión está documentada solo para el acceso con Google (`routes/web.php`, CLAUDE.md §2); no se extendió a nada más.
- **Impacto:** cualquier cliente puede agotar los workers de PHP-FPM con peticiones al panel, a descargas o a la generación de PDF/Excel. Una petición de 36 MB y los reportes son los puntos más costosos.
- **Recomendación (en capas):**
  - **Red (obligatorio, lo decide la universidad):** proxy inverso con WAF/protección DDoS delante del servidor; un ataque volumétrico no se detiene dentro de la máquina.
  - **nginx:** `limit_req_zone`/`limit_conn_zone` con umbrales generosos (la salida común de la universidad concentra cientos de usuarios en una IP), tiempos de espera cortos (`client_body_timeout`, `client_header_timeout`) y un límite más estricto en `/livewire/update` y las descargas.
  - **Aplicación:** `throttle` por usuario autenticado (no por IP) en descargas, reportes y exportaciones.
  - **PHP-FPM:** `pm.max_children` acotado y `request_terminate_timeout`.

### S-03 · Datos sensibles sin cifrado adicional en reposo ni TLS hacia la base — **ALTO** · Abierto
- **Categoría:** OWASP A02:2021 (fallos criptográficos) / ASVS V6, V9.
- **Evidencia:** no hay casts `encrypted` en `app/Models`. No hay `sslmode` ni certificados en la configuración de la base. Los volúmenes `datos_postgres` y `archivos_privados` y el directorio de copias no tienen cifrado declarado en el compose.
- **Impacto:** quien lea el disco, el volumen o una copia obtiene nombres, correos, resultados de evaluaciones y los formatos firmados (datos personales sujetos a la Ley 1581 de 2012).
- **Recomendación:**
  1. Cifrado del disco o del volumen (LUKS en el servidor) y de las copias (`age`/`gpg`) antes de salir del servidor.
  2. Cast `encrypted` en las columnas que el cliente defina como "muy sensibles". Esas columnas no se pueden buscar ni ordenar en SQL; decidirlas con el cliente.
  3. TLS entre `app` y PostgreSQL (`ssl=on` y `DB_SSLMODE=require`) si la base sale algún día del compose.
  4. Crear un rol de base distinto del dueño para la aplicación, sin `CREATE`/`DROP` en el uso normal; el de migraciones solo en el despliegue.

### S-04 · IDs autoincrementales: protección solo por Policy — **MEDIO** · Mitigado parcialmente
- **Categoría:** OWASP A01:2021 (control de acceso roto, IDOR) / ASVS V4.2.
- **Evidencia:** las llaves son `id()` secuenciales; rutas como `/panel/solicitudes/{n}` y `/panel/usuarios/{n}/editar` son enumerables. Las defensas existentes son las Policies y que los parámetros sean numéricos.
- **Riesgo residual:** (a) una ruta nueva sin Policy sería enumerable al instante; (b) diferencias entre 403 y 404 revelan qué ids existen; (c) los ids de los archivos firmados incluyen `firmante_id`.
- **Recomendación:** (1) un test que recorra todas las rutas con parámetro de modelo y falle si no hay `can`/`authorize`; (2) responder 404 en lugar de 403 cuando la persona no puede ni saber que el registro existe; (3) ids públicos no secuenciales (UUID/ULID) para formatos, evaluaciones y cuentas; (4) quitar `firmante_id` del nombre de descarga.

### S-05 · CSP con `unsafe-inline` y `unsafe-eval` — **MEDIO** · Riesgo aceptado
- **Categoría:** OWASP A05:2021.
- **Evidencia:** `CabecerasDeSeguridad::politicaDeContenido()`. Documentado: Alpine y Filament lo necesitan.
- **Impacto:** si algún día aparece un XSS, la CSP no lo frena. Hoy no se encontró ninguna salida sin escapar.
- **Recomendación:** evaluar la build "CSP" de Alpine con nonces cuando Filament lo permita; mientras tanto mantener la regla "nada sin escapar" con un test que falle ante `{!!`.

### S-06 · Cookies de sesión y HTTPS detrás de un proxy — **MEDIO** · Por verificar
- **Categoría:** OWASP A07:2021 / ASVS V3.
- **Evidencia:** `config/session.php`: `secure` depende de `SESSION_SECURE_COOKIE` (sin valor por defecto), `SESSION_ENCRYPT=false`, duración 120 min. `config/trustedproxy.php`: `proxies` es `null` salvo que se defina `PROXIES_DE_CONFIANZA`. nginx escucha solo en HTTP; el HTTPS lo termina el proxy de la universidad.
- **Riesgo:** sin proxies de confianza, Laravel no reconoce que la petición es segura; entonces **no manda HSTS** y puede generar URLs `http://`. Sin `SESSION_SECURE_COOKIE=true`, la cookie podría viajar sin cifrar si alguien entra por HTTP.
- **Recomendación:** en producción fijar `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `PROXIES_DE_CONFIANZA` con la IP del proxy, y añadir un test de configuración de producción que lo exija. Redirigir HTTP→HTTPS en el proxy.

### S-07 · Endurecimiento de contenedores y de imágenes — **MEDIO** · Abierto
- **Categoría:** OWASP A05:2021 / CIS Docker Benchmark.
- **Evidencia:** imágenes por tag móvil (`php:8.3-fpm-bookworm`, `postgres:16`, `nginx:1.27-alpine`, `composer:2`), sin digest. Sin `read_only`, `cap_drop: [ALL]` ni `security_opt: no-new-privileges` en el compose. La base (`db`) comparte red con todos los servicios. Los procesos de PHP corren como `www-data` (bien).
- **Recomendación:** fijar versiones por digest y actualizarlas con una revisión mensual; añadir `no-new-privileges`, `cap_drop: [ALL]`, sistema de archivos de solo lectura con `tmpfs` para lo que escribe; dos redes (`frontal` para nginx↔app, `interna` para app↔db); escáner de imágenes (Trivy) en la CI.

### S-08 · Registro y detección de incidentes limitados — **BAJO** · Abierto
- **Categoría:** OWASP A09:2021 / ASVS V7.
- **Evidencia:** solo hay una llamada a `Log::` en `app/`. La bitácora (RF62) audita acciones de negocio, no intentos fallidos (403, entradas rechazadas, descargas de documentos). No hay alertas ni centralización; los logs de Docker rotan localmente (10 MB × 5).
- **Recomendación:** registrar 401/403/419/429, descargas de formatos y accesos a `/admin`; reenviar a un colector del servidor institucional; alertas ante picos de errores 403/5xx.

### S-09 · La CI no cubre toda la cadena de suministro — **BAJO** · Abierto
- **Categoría:** OWASP A06:2021 / A08:2021.
- **Evidencia:** la CI ejecuta `composer audit` pero no `npm audit`, ni escaneo de secretos, ni de imágenes, ni Dependabot.
- **Recomendación:** añadir `npm audit --omit=dev`, `gitleaks`, Trivy sobre las imágenes de producción y Dependabot (Composer, npm, Docker, Actions).

### S-10 · Superficie administrativa en el mismo host — **INFORMATIVO**
- `/admin` (Filament) está en el mismo dominio que el panel. El acceso lo decide el Gate `accederAlPanelDelAdmin` con el rol activo, y no tiene entrada propia de autenticación. Es una buena práctica; se recomienda además restringirlo por red (VPN o lista de IP en el proxy) si la universidad lo permite.

---

## 5. Correspondencia con OWASP Top 10:2021

| Categoría | Estado | Hallazgos |
|---|---|---|
| A01 Control de acceso roto | Bueno, con riesgo residual | S-04 |
| A02 Fallos criptográficos | **Débil** | S-03, S-06 |
| A03 Inyección | Bueno | — |
| A04 Diseño inseguro | **Débil** | S-02 |
| A05 Configuración de seguridad incorrecta | Aceptable | S-05, S-07 |
| A06 Componentes vulnerables | **Crítico** hasta aplicar el parche | S-01, S-09 |
| A07 Fallos de identificación y autenticación | Bueno | S-06 |
| A08 Integridad de software y datos | Aceptable | S-09 |
| A09 Registro y monitoreo | Limitado | S-08 |
| A10 SSRF | No aplica: la aplicación no pide URLs enviadas por usuarios | — |

---

## 6. Referencias

- CISA, *Critical Vulnerabilities in PHP Require Immediate Patching* — https://www.cisa.gov/news-events/alerts/2026/10/21/critical-vulnerabilities-php-require-immediate-patching
- Help Net Security, *PHP2Shell: Critical PHP vulnerabilities patched* — https://www.helpnetsecurity.com/2026/10/21/php2shell-php-vulnerabilities-patched-cve-2026-109618-cve-2026-109619/
- Tenable, *php2shell FAQ* — https://www.tenable.com/blog/php2shell-cve-2026-109618-cve-2026-109619-frequently-asked-questions-about-critical-php-remote
- Datadog Security Labs, *PHP2Shell* — https://securitylabs.datadoghq.com/articles/php2shell-critical-vulnerabilities-in-php/
- OWASP Top 10:2021 · OWASP ASVS 4.0 · NIST SP 800-30 · CIS Docker Benchmark

*Nota:* los datos de PHP2Shell provienen de fuentes secundarias; se recomienda contrastarlos con el aviso oficial de php.net antes de cerrar el hallazgo S-01.

---

## 7. Plan de remediación

| Prioridad | Acción | Hallazgo | Responsable sugerido |
|:--:|---|---|---|
| Hoy | Reconstruir con PHP ≥ 8.3.36, verificar y reiniciar; rotar secretos si hubo exposición | S-01 | Desarrollo y administrador del servidor |
| Esta semana | `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT`, `PROXIES_DE_CONFIANZA`; límites en nginx y PHP-FPM | S-02, S-06 | Desarrollo |
| Esta semana | Pedir a la universidad proxy con WAF/anti-DDoS y HTTP→HTTPS | S-02 | Administrador del servidor |
| 2 semanas | Cifrado de disco y de copias; decidir columnas cifradas con el cliente | S-03 | Administrador y cliente |
| 2 semanas | Test que obligue Policy en toda ruta con modelo; 404 en lugar de 403 | S-04 | Desarrollo |
| 1 mes | Endurecer contenedores; Trivy, gitleaks, `npm audit` y Dependabot en la CI | S-07, S-09 | Desarrollo |
| 1 mes | Registro de accesos denegados y descargas, con alertas | S-08 | Desarrollo y administrador |

---

## 8. Reevaluación

Este informe describe el estado **al 22-oct-2026**. Al cerrar cada hallazgo se actualizará la tabla de §4 con la fecha y el commit de la corrección. Se recomienda una prueba de penetración externa antes de entrar en producción con datos reales, y revisiones trimestrales de dependencias e imágenes.
