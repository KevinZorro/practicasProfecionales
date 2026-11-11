-- bandeja-vencidas: Pendientes o revisadas cuya fecha ya pasó (P6).
-- Origen: app/Services/SolicitudService.php:474 vencidas
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set pagina random(0, 40) * 15

select count(*) as aggregate from "solicitudes" where "solicitudes"."estado" in ('pendiente', 'revisada') and "solicitudes"."fecha"::date < CURRENT_DATE;

select * from "solicitudes" where "solicitudes"."estado" in ('pendiente', 'revisada') and "solicitudes"."fecha"::date < CURRENT_DATE order by "fecha" asc, "hora_inicio" asc, "id" asc limit 15 offset :pagina;

select "id", "nombre" from "users" where "users"."id" in (49, 64, 89, 162, 205, 225, 266, 308, 337, 410, 453, 617, 630, 645, 719);

select * from "materias" where "materias"."id" in (1, 3, 6, 7, 8, 9, 11, 12);

select * from "casos_clinicos" where "casos_clinicos"."id" in (1, 3, 7, 9, 12, 15, 19, 20, 22, 30, 32);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (11, 12, 19, 24, 62, 76, 86, 100, 107, 108, 113, 124, 135, 157, 162);
