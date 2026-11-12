-- bandeja-pendientes: Bandeja de revisión/aprobación, por estado, con su conteo y la carga anticipada.
-- Origen: app/Services/SolicitudService.php:456 bandeja
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set pagina random(0, 40) * 15

select count(*) as aggregate from "solicitudes" where ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE) and "estado" = 'pendiente';

select * from "solicitudes" where ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE) and "estado" = 'pendiente' order by "fecha" asc, "hora_inicio" asc, "id" asc limit 15 offset :pagina;

select "id", "nombre" from "users" where "users"."id" in (18, 26, 46, 60, 64, 107, 109, 121, 122, 125, 126, 130, 146, 159);

select * from "materias" where "materias"."id" in (2, 4, 5, 7, 8, 9, 10, 11, 12);

select * from "casos_clinicos" where "casos_clinicos"."id" in (5, 11, 15, 16, 22, 24, 29, 31, 34, 35, 38, 40);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (4093, 4096, 4098, 4099, 4100, 4103, 4104, 4106, 4109, 4110, 4120, 4123, 4129, 4135, 4142);
