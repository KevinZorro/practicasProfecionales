# 0008. Las pruebas de rendimiento corren fuera de la suite, con k6 y pgbench en contenedores

- Fecha: 2026-11-11
- Estado: Aceptada

## Contexto

Los escenarios de calidad de RNF01 (p95 ≤ 2 s con 150 personas en 5 minutos) y RNF10 (≤ 300 KB en la primera carga, en 3G) estaban **sin medir** (`docs/escenarios-de-calidad.md`), y la auditoría de arquitectura pidió una prueba de carga fuera de la suite, contra el compose de producción (B6.3). Una prueba de carga no cabe en Pest: dura minutos, necesita nginx, PHP-FPM y PostgreSQL de verdad, y su resultado depende de la máquina.

Tres condiciones del proyecto pesan en la elección: la aplicación no debe sumar dependencias (CLAUDE.md §2), quien la mantendrá conoce PHP pero no herramientas de prueba de carga, y la única entrada es Google (RF18), por la que una prueba no puede pasar ciento cincuenta veces.

## Decisión

- **Las pruebas viven en `rendimiento/`**, fuera de `tests/` y fuera de la imagen de producción (`.dockerignore`). Se corren contra el compose de producción, con un archivo que lo complementa solo para medir (`rendimiento/docker-compose.rendimiento.yml`).
- **HTTP y navegador con k6** (`grafana/k6` con la versión fijada, también la imagen con navegador), **base de datos con pgbench** (el de la imagen `postgres:16`). Todo por contenedor: no se instala nada en el servidor ni entra nada en `composer.json` o `package.json`.
- **Las pruebas entran con sesiones creadas directamente en el almacén de sesiones** (`rendimiento/herramientas/crear-sesiones.php`), con la cookie cifrada con `APP_KEY` como la que deja la entrada real. El script se niega a correr sin `RENDIMIENTO_BASE_DESECHABLE=si`.
- **Los datos son sintéticos y del tamaño real**: dos semestres más el vigente (P13) con la población de RNF01, y ×5 para la escalabilidad (`rendimiento/datos/generar-volumen.php`), sin Faker, para que corra también dentro de la imagen de producción.
- **El veredicto del RNF01 se lee en nginx** (`rt=$request_time` en el log, `rendimiento/herramientas/resumir-nginx.php`), donde lo define el escenario; k6 da el mismo dato visto desde el cliente.
- **Lo que sí vive en la suite** es lo determinista: el presupuesto de consultas por pantalla (`tests/Feature/Rendimiento`), que falla en la CI si aparece un N+1.

## Alternativas consideradas

- **JMeter.** El estándar de facto más antiguo, pero Java, planes en XML y una interfaz gráfica: más difícil de leer y revisar en un PR que un script.
- **Locust o Artillery.** Buenos, pero suman Python o un paquete de npm al proyecto.
- **Playwright y Lighthouse para el navegador.** Más conocidos que el navegador de k6, pero serían dependencias de npm y una segunda herramienta con otro lenguaje de resultados. k6 con navegador da las Core Web Vitals con las mismas métricas, umbrales y resumen que la prueba HTTP.
- **Pasar por la entrada de desarrollo** (`/desarrollo/acceso`). Solo existe en `local`, así que obligaría a medir una aplicación que no es la de producción (rutas, configuración y caché distintas).
- **Medir solo con Pest** (tiempos dentro de la suite). Sin nginx ni PHP-FPM ni concurrencia real, el número no dice nada del RNF01.

## Consecuencias

- Gana: los escenarios de calidad tienen una prueba que se puede repetir en el servidor institucional antes de entrar en producción y después de cada cambio grande, con un solo procedimiento (`rendimiento/README.md`).
- Sacrifica: una herramienta más que aprender (k6, en JavaScript). Se compensa con scripts cortos, comentados y con la misma estructura.
- **El script de sesiones es una llave del sistema.** Solo se corre contra bases desechables; nunca contra la de producción con personas reales.
- **Las cifras dependen del equipo donde se mide.** El informe de cada campaña dice en qué máquina se corrió; las del entorno de pruebas no sustituyen a las del servidor institucional.

**Señal de revisión:** si el servidor institucional necesita más de una instancia de la aplicación, o si la medición en producción (log de nginx) deja de cumplir el RNF01, las pruebas pasan a correr también en la CI con un umbral propio.
