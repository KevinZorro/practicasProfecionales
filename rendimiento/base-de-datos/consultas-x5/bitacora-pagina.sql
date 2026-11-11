-- bitacora-pagina: Bitácora, la más reciente primero, página cualquiera (de 25 en 25).
-- Origen: app/Services/BitacoraService.php:77 listado
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set pagina random(0, 400) * 25

select count(*) as aggregate from "bitacora";

select * from "bitacora" order by "created_at" desc, "id" desc limit 25 offset :pagina;

select "id", "nombre" from "users" where "users"."id" in (24, 35, 102, 197, 221, 362, 373, 400, 430, 443, 448, 498, 507, 525, 534, 580, 584, 666, 669, 675, 676, 704, 710, 723, 736);
