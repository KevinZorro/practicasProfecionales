# Escenarios de calidad

Los requisitos no funcionales con un número contra el cual aceptarlos. RNF01, RNF02 y RNF10 decían "soportar la población real", "disponible durante el horario académico" y "aceptable en conexiones de baja velocidad", sin medida. Las cifras de abajo las confirmó el desarrollador el 2026-11-07, a partir de la auditoría de arquitectura (B0.8).

Cada escenario tiene seis partes: quién o qué lo provoca (fuente), qué pasa (estímulo), sobre qué parte del sistema (artefacto), en qué condiciones (entorno), qué hace el sistema (respuesta) y cómo se mide.

## Atributos de los RNF

| Atributo | Fuente | Estímulo | Artefacto | Entorno | Respuesta | Medida | Cómo se comprueba hoy |
|---|---|---|---|---|---|---|---|
| Rendimiento (RNF01) | 150 personas a la vez | Abren el panel y el calendario en 5 minutos | `app` y PostgreSQL | Producción, pico de las 7:00 | Páginas servidas | p95 ≤ 2 s, medido en nginx (`$request_time`) en una ventana de 5 minutos, sin 429 ni 5xx | **Sin medir.** Falta registrar `$request_time` en el log de nginx y una prueba de carga fuera de la suite |
| Disponibilidad (RNF02) | Cualquier usuario | Pide `/up` | La plataforma completa | De lunes a viernes de 6:00 a 20:00 y los sábados de 6:00 a 12:00 | Responde 200 | ≥ 99 % de los minutos de ese horario en el semestre, medido cada minuto por un monitor externo | **Sin medir.** Falta el monitor externo (README, «Vigilancia») |
| Conexión lenta (RNF10) | Un administrativo en el celular | Primera carga del panel | HTML y assets | Red 3G | Pantalla usable | ≤ 300 KB comprimidos en la primera carga | **Sin prueba.** La CI ya comprueba que nginx comprime los assets |

## Escenarios de la auditoría

Ya tienen su prueba, y cada una falló antes del arreglo.

| Atributo | Fuente | Estímulo | Artefacto | Entorno | Respuesta | Medida | Prueba |
|---|---|---|---|---|---|---|---|
| Seguridad (B4.5) | Alguien con sesión y malas intenciones | Cambia el ID para pedir algo que no le corresponde | Rutas y acciones del panel | Operación normal | 403, y queda en la bitácora quién, qué y sobre qué registro | 0 accesos indebidos y el 100 % de los intentos registrados, en todas las rutas del panel con ID | `AccesosNegadosTest` |
| Consistencia (B3.11) | Dos administrativos | Asignan la misma sala a la vez a sesiones que se cruzan | `PreparacionService::asignarSalas` | Montaje de las 6:30 | La segunda asignación ve la primera y se rechaza | 0 salas con sesiones que se cruzan | `AsignacionDeSalaConcurrenteTest` |
| Disponibilidad (B3.1) | Google | Responde lento o con error | La entrada con Google | Pico de entradas | Mensaje para reintentar, nunca un 500 | Como mucho 5 s, y 3 s para conectar, por entrada; el resto del panel sigue atendiendo | `AccesoConGoogleTest`, sección «Google lento o con error» |
