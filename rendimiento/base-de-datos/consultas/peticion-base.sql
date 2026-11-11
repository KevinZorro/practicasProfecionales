-- peticion-base: Lo que toda petición autenticada del panel hace antes de pintar: leer sesión, usuario y roles, y escribir la sesión.
-- Origen: SessionGuard + User::roles() + DatabaseSessionHandler
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set sid random(1, 1000)
\set uid random(2, 161)

select * from "sessions" where "id" = ('rend-' || :sid) limit 1;

select * from "users" where "id" = :uid limit 1;

select "roles".*, "model_has_roles"."model_id" as "pivot_model_id", "model_has_roles"."role_id" as "pivot_role_id", "model_has_roles"."model_type" as "pivot_model_type", "model_has_roles"."desde" as "pivot_desde", "model_has_roles"."hasta" as "pivot_hasta" from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where ("model_has_roles"."desde" is null or "model_has_roles"."desde" <= CURRENT_DATE) and ("model_has_roles"."hasta" is null or "model_has_roles"."hasta" >= CURRENT_DATE) and "model_has_roles"."model_id" in (92) and "model_has_roles"."model_type" = 'App\Models\User';

update "sessions" set "payload" = repeat('a', 1600), "last_activity" = extract(epoch from now())::int, "user_id" = :uid, "ip_address" = '127.0.0.1', "user_agent" = 'Symfony' where "id" = ('rend-' || :sid);
