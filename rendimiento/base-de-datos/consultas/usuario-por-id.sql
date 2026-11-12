-- usuario-por-id: El usuario de la sesión: se lee en cada petición autenticada.
-- Origen: Illuminate\Auth\SessionGuard::user() (EloquentUserProvider)
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set uid random(2, 161)

select * from "users" where "id" = :uid limit 1;
