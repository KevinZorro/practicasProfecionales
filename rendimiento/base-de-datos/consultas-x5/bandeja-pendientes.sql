-- bandeja-pendientes: Bandeja de revisión/aprobación, por estado, con su conteo y la carga anticipada.
-- Origen: app/Services/SolicitudService.php:456 bandeja
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set pagina random(0, 40) * 15

select count(*) as aggregate from "solicitudes" where ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE) and "estado" = 'pendiente';

select * from "solicitudes" where ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE) and "estado" = 'pendiente' order by "fecha" asc, "hora_inicio" asc, "id" asc limit 15 offset :pagina;

select "id", "nombre" from "users" where "users"."id" in (99, 132, 133, 138, 163, 368, 372, 406, 483, 540, 595, 604, 619, 623, 637);

select * from "materias" where "materias"."id" in (1, 2, 3, 5, 7, 8, 9, 11);

select * from "casos_clinicos" where "casos_clinicos"."id" in (1, 5, 7, 12, 20, 21, 23, 27, 34, 35, 40);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (18929, 18935, 18936, 18937, 18945, 18949, 18950, 18951, 18954, 18955, 18959, 18960, 18961, 18969, 18981);
