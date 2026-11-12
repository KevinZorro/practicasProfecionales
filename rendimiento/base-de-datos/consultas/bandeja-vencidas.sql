-- bandeja-vencidas: Pendientes o revisadas cuya fecha ya pasó (P6).
-- Origen: app/Services/SolicitudService.php:474 vencidas
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set pagina random(0, 40) * 15

select count(*) as aggregate from "solicitudes" where "solicitudes"."estado" in ('pendiente', 'revisada') and "solicitudes"."fecha"::date < CURRENT_DATE;

select * from "solicitudes" where "solicitudes"."estado" in ('pendiente', 'revisada') and "solicitudes"."fecha"::date < CURRENT_DATE order by "fecha" asc, "hora_inicio" asc, "id" asc limit 15 offset :pagina;

select "id", "nombre" from "users" where "users"."id" in (14, 22, 25, 32, 38, 40, 43, 92, 97, 116, 124, 133, 135);

select * from "materias" where "materias"."id" in (2, 3, 5, 6, 7, 8, 9, 12);

select * from "casos_clinicos" where "casos_clinicos"."id" in (1, 2, 3, 5, 7, 10, 14, 17, 19, 21, 26, 28, 37, 40);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (7, 28, 123, 141, 161, 173, 252, 269, 274, 343, 386, 391, 400, 422, 431);
