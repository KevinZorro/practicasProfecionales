# Escenarios de calidad

Los requisitos no funcionales con un número contra el cual aceptarlos. RNF01, RNF02 y RNF10 decían "soportar la población real", "disponible durante el horario académico" y "aceptable en conexiones de baja velocidad", sin medida. Las cifras de abajo las confirmó el desarrollador el 2026-11-07, a partir de la auditoría de arquitectura (B0.8).

Cada escenario tiene seis partes: quién o qué lo provoca (fuente), qué pasa (estímulo), sobre qué parte del sistema (artefacto), en qué condiciones (entorno), qué hace el sistema (respuesta) y cómo se mide.

## Atributos de los RNF

| Atributo | Fuente | Estímulo | Artefacto | Entorno | Respuesta | Medida | Cómo se comprueba hoy |
|---|---|---|---|---|---|---|---|
| Rendimiento (RNF01) | 150 personas a la vez | Abren el panel y el calendario en 5 minutos | `app` y PostgreSQL | Producción, pico de las 7:00 | Páginas servidas | p95 ≤ 2 s, medido en nginx (`$request_time`) en una ventana de 5 minutos, sin 429 ni 5xx | **Medido en el entorno de pruebas** (4 vCPU, con 3 para el sistema y el generador en la misma máquina; no es el servidor institucional): `rendimiento/k6/carga-rnf01.js` más el log de nginx (`rt=` en `$request_time`). Con 150 personas en 5 minutos desde una sola IP: p95 0,061 s, 0 respuestas 429 y 0 5xx; con 300 usuarios y la mezcla de la hora pico, p95 0,216 s. Informe: [`rendimiento/informe-2026-11-11.md`](rendimiento/informe-2026-11-11.md). Falta repetirlo en el servidor institucional antes de entrar en producción, con la IP real del proxy (`PROXIES_DE_CONFIANZA`): con una sola IP, un pico de 300 usuarios recibe 429 |
| Disponibilidad (RNF02) | Cualquier usuario | Pide `/up` | La plataforma completa | De lunes a viernes de 6:00 a 20:00 y los sábados de 6:00 a 12:00 | Responde 200 | ≥ 99 % de los minutos de ese horario en el semestre, medido cada minuto por un monitor externo | **Sin medir.** Falta el monitor externo (README, «Vigilancia») |
| Conexión lenta (RNF10) | Un administrativo en el celular | Primera carga del panel | HTML y assets | Red 3G | Pantalla usable | ≤ 300 KB comprimidos en la primera carga | **Medido en el entorno de pruebas** con Chromium real (k6 browser, `rendimiento/navegador/web-vitals.js`), perfil de celular 3G de gama baja (1,6 Mbps, 300 ms de RTT, CPU 4× más lenta): de 112,9 a 117,3 KB en la primera carga de las pantallas operativas y 189,3 KB en el calendario, la más pesada (bytes de `transferSize`; 1 KB = 1.024 B); LCP de 1,0 a 1,4 s. Informe: [`rendimiento/informe-2026-11-11.md`](rendimiento/informe-2026-11-11.md). La CI además comprueba que nginx comprime los assets. Las cifras de tiempo son de esa máquina; los bytes no dependen de ella |

## Escenarios de la auditoría

Ya tienen su prueba, y cada una falló antes del arreglo.

| Atributo | Fuente | Estímulo | Artefacto | Entorno | Respuesta | Medida | Prueba |
|---|---|---|---|---|---|---|---|
| Seguridad (B4.5) | Alguien con sesión y malas intenciones | Cambia el ID para pedir algo que no le corresponde | Rutas y acciones del panel | Operación normal | 403, y queda en la bitácora quién, qué y sobre qué registro | 0 accesos indebidos y el 100 % de los intentos registrados, en todas las rutas del panel con ID | `AccesosNegadosTest` |
| Consistencia (B3.11) | Dos administrativos | Asignan la misma sala a la vez a sesiones que se cruzan | `PreparacionService::asignarSalas` | Montaje de las 6:30 | La segunda asignación ve la primera y se rechaza | 0 salas con sesiones que se cruzan | `AsignacionDeSalaConcurrenteTest` |
| Disponibilidad (B3.1) | Google | Responde lento o con error | La entrada con Google | Pico de entradas | Mensaje para reintentar, nunca un 500 | Como mucho 5 s, y 3 s para conectar, por entrada; el resto del panel sigue atendiendo | `AccesoConGoogleTest`, sección «Google lento o con error» |
