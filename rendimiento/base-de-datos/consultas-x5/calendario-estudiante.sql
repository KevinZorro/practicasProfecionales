-- calendario-estudiante: Calendario de un mes (solo donde está en la lista (whereHas)).
-- Origen: app/Services/SolicitudService.php:593 paraCalendario
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set off random(-440, 10)
\set uid random(762, 5761)

select * from "solicitudes" where "estado" = 'aprobada' and "fecha" between (CURRENT_DATE + :off) and (CURRENT_DATE + :off + 30) and exists (select * from "users" inner join "estudiante_solicitud" on "users"."id" = "estudiante_solicitud"."estudiante_id" where "solicitudes"."id" = "estudiante_solicitud"."solicitud_id" and "users"."id" = :uid and "estudiante_solicitud"."retirado_at" is null) order by "fecha" asc, "hora_inicio" asc, "id" asc;

select "id", "nombre" from "users" where "users"."id" in (99, 168, 286, 321, 422, 453, 512, 617, 710, 738, 743, 755);

select * from "materias" where "materias"."id" in (1, 2, 3, 4, 5, 7, 8, 12);

select * from "casos_clinicos" where "casos_clinicos"."id" in (2, 8, 9, 10, 11, 12, 14, 15, 17, 29, 39, 40);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (18184, 18201, 18426, 18806, 18855, 18915, 19020, 19046, 19378, 19417, 19762, 19791);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (13143, 13155, 13318, 13597, 13636, 13678, 13721, 13729, 13834, 13847, 13965, 13981) order by "salas"."nombre" asc;
