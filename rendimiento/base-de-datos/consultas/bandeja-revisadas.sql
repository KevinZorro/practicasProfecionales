-- bandeja-revisadas: Bandeja de revisión/aprobación, por estado, con su conteo y la carga anticipada.
-- Origen: app/Services/SolicitudService.php:456 bandeja
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set pagina random(0, 40) * 15

select count(*) as aggregate from "solicitudes" where ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE) and "estado" = 'revisada';

select * from "solicitudes" where ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE) and "estado" = 'revisada' order by "fecha" asc, "hora_inicio" asc, "id" asc limit 15 offset :pagina;

select "id", "nombre" from "users" where "users"."id" in (15, 21, 40, 43, 51, 53, 68, 84, 90, 107, 122, 128, 138, 141, 150);

select * from "materias" where "materias"."id" in (1, 2, 4, 5, 6, 9, 10, 12);

select * from "casos_clinicos" where "casos_clinicos"."id" in (5, 8, 13, 21, 23, 24, 26, 29, 30, 33, 34, 37, 38, 40);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (4092, 4094, 4095, 4115, 4116, 4119, 4125, 4127, 4128, 4137, 4141, 4149, 4151, 4159, 4163);
