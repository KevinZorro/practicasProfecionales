-- bitacora-pagina: Bitácora, la más reciente primero, página cualquiera (de 25 en 25).
-- Origen: app/Services/BitacoraService.php:77 listado
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set pagina random(0, 400) * 25

select count(*) as aggregate from "bitacora";

select * from "bitacora" order by "created_at" desc, "id" desc limit 25 offset :pagina;

select "id", "nombre" from "users" where "users"."id" in (22, 26, 33, 42, 44, 45, 49, 55, 59, 62, 63, 69, 79, 80, 86, 94, 101, 106, 109, 140, 141, 145, 153, 155);
