# 0006. El HTTPS lo termina el proxy de la universidad

- Fecha: 2026-11-07
- Estado: Aceptada. Falta la IP del proxy

## Contexto

El servidor institucional queda detrás de un proxy de la universidad, que es el que termina el HTTPS (lo confirmó el desarrollador el 2026-11-07). Todavía no se conoce la IP de ese proxy.

## Decisión

El nginx del compose escucha por HTTP en el puerto 80 y no lleva certificado.

La IP del proxy se configura en un solo sitio, `PROXIES_DE_CONFIANZA` del `.env`, y vale para dos cosas:

- **Laravel** confía en las cabeceras `X-Forwarded-*` que manda el proxy (`config/trustedproxy.php`). Así sabe que la petición original era segura y genera enlaces `https://`, manda HSTS y marca la cookie como segura.
- **nginx** toma la IP real del visitante de `X-Forwarded-For` (`set_real_ip_from`), pero solo cuando la petición viene del proxy. Lo genera `docker/produccion/ip-real.sh` al arrancar.

## Alternativas consideradas

- **Un certificado propio en nginx.** No aplica: el HTTPS ya lo termina el proxy.
- **Confiar en cualquiera (`*`).** Solo es seguro si nadie más puede llegar al nginx del servidor.

## Consecuencias

- Gana: no hay un certificado que renovar en el servidor.
- Mientras falte la IP, Laravel cree que las peticiones llegan por HTTP: genera enlaces `http://` y no manda HSTS.
- Mientras falte la IP, nginx cuenta a todos los visitantes como una sola dirección. Sus topes por IP pasan a ser de toda la universidad: 100 peticiones por segundo y 100 conexiones (B3.8 de la auditoría).
- La cadena tiene una pieza más que el laboratorio no controla.

**Pendiente con sistemas de la UFPS:** la IP del proxy, y si manda `X-Forwarded-For` y `X-Forwarded-Proto`.
