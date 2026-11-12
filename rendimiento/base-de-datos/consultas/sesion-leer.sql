-- sesion-leer: Lectura de la sesión (almacén sessions en PostgreSQL), una por petición.
-- Origen: Illuminate\Session\DatabaseSessionHandler::read()
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set sid random(1, 1000)
\set uid random(2, 161)

select * from "sessions" where "id" = ('rend-' || :sid) limit 1;
