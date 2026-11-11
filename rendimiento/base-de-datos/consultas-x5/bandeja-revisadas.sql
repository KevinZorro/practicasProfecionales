-- bandeja-revisadas: Bandeja de revisión/aprobación, por estado, con su conteo y la carga anticipada.
-- Origen: app/Services/SolicitudService.php:456 bandeja
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set pagina random(0, 40) * 15

select count(*) as aggregate from "solicitudes" where ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE) and "estado" = 'revisada';

select * from "solicitudes" where ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE) and "estado" = 'revisada' order by "fecha" asc, "hora_inicio" asc, "id" asc limit 15 offset :pagina;

select "id", "nombre" from "users" where "users"."id" in (14, 123, 150, 188, 251, 422, 472, 485, 499, 529, 607, 631, 638, 689, 714);

select * from "materias" where "materias"."id" in (1, 2, 3, 4, 5, 8, 9, 10, 11);

select * from "casos_clinicos" where "casos_clinicos"."id" in (3, 4, 6, 8, 9, 10, 13, 14, 16, 24, 29, 35, 38);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (18930, 18931, 18938, 18939, 18940, 18941, 18947, 18952, 18953, 18962, 18963, 18966, 18968, 18970, 18971);
