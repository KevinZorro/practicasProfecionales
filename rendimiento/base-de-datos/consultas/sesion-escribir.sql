-- sesion-escribir: Escritura de la sesión, una por petición (UPDATE de la fila, con payload).
-- Origen: Illuminate\Session\DatabaseSessionHandler::write()
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set sid random(1, 1000)
\set uid random(2, 161)

update "sessions" set "payload" = repeat('a', 1600), "last_activity" = extract(epoch from now())::int, "user_id" = :uid, "ip_address" = '127.0.0.1', "user_agent" = 'Symfony' where "id" = ('rend-' || :sid);
