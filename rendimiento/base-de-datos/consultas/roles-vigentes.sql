-- roles-vigentes: Relación de roles filtrada por vigencia (regla 13): se resuelve en cada can()/hasRole() de la petición.
-- Origen: app/Models/User.php:104 User::roles()
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set uid random(2, 161)

select "roles".*, "model_has_roles"."model_id" as "pivot_model_id", "model_has_roles"."role_id" as "pivot_role_id", "model_has_roles"."model_type" as "pivot_model_type", "model_has_roles"."desde" as "pivot_desde", "model_has_roles"."hasta" as "pivot_hasta" from "roles" inner join "model_has_roles" on "roles"."id" = "model_has_roles"."role_id" where ("model_has_roles"."desde" is null or "model_has_roles"."desde" <= CURRENT_DATE) and ("model_has_roles"."hasta" is null or "model_has_roles"."hasta" >= CURRENT_DATE) and "model_has_roles"."model_id" in (92) and "model_has_roles"."model_type" = 'App\Models\User';
