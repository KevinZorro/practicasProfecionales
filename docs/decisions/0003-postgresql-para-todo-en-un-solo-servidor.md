# 0003. PostgreSQL para todo, en un solo servidor

- Fecha: 2026-11-07 (registrada después de implementarse)
- Estado: Aceptada

## Contexto

La plataforma la usan unas 850 personas (RNF01). Corre en un servidor institucional propio, con Debian 13 y Docker, sin servicios de pago. Después del desarrollo la mantiene una sola persona, que conoce PHP.

## Decisión

PostgreSQL 16 es el único motor. Guarda los datos, las sesiones, la caché, la cola (tabla `jobs`) y los candados (`lockForUpdate` y `pg_advisory_xact_lock`).

Todo corre en un solo servidor, con Docker Compose:

- `app`: PHP-FPM, con 20 workers.
- `queue` y `programador`: la cola y las tareas programadas.
- `web`: nginx.
- `db`: PostgreSQL.
- `copias`: copia diaria.

Los archivos van en volúmenes locales y las copias quedan en el mismo servidor. La elección del monolito tiene su porqué en `arquitectura-y-modelo-datos.md` §1.

## Alternativas consideradas

- **Redis para las sesiones, la caché y la cola.** Sería otro servicio que operar y respaldar, sin una necesidad medida.
- **Una nube administrada.** Es de pago.
- **Varios servidores.** Obligaría a compartir las sesiones y a sacar los archivos a un almacenamiento de objetos.

## Consecuencias

- Gana: un solo motor que operar y respaldar.
- Gana: como la cola está en la misma base, un trabajo encolado dentro de una transacción se confirma o se descarta junto con ella.
- Sacrifica: toda la capacidad depende de 20 workers y de una sola base. Una exportación grande o una tanda de entradas lentas ocupan workers que necesitan todos los demás.
- Sacrifica: con los archivos en el disco local no se puede añadir una segunda instancia sin moverlos antes.
- Sacrifica: una copia guardada en el mismo disco se pierde con él. Falta llevar las copias fuera del servidor.

**Señal de revisión:** cualquiera de estas tres, y la respuesta va en ese orden: primero, una cola aparte para las exportaciones; después, sesiones y archivos fuera del disco local.

- Un p95 de más de 2 s en el pico de las 7:00 (escenario de RNF01, `docs/escenarios-de-calidad.md`).
- `max_children reached` en el registro de PHP-FPM.
- La necesidad de una segunda instancia.
