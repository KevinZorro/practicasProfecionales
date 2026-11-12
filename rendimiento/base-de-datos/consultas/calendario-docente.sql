-- calendario-docente: Calendario de un mes (solo las suyas o las que dicta).
-- Origen: app/Services/SolicitudService.php:593 paraCalendario
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set off random(-440, 10)
\set uid random(2, 161)

select * from "solicitudes" where "estado" = 'aprobada' and "fecha" between (CURRENT_DATE + :off) and (CURRENT_DATE + :off + 30) and ("docente_id" = :uid or "docente_que_dicta_id" = :uid) order by "fecha" asc, "hora_inicio" asc, "id" asc;

select "id", "nombre" from "users" where "users"."id" in (92);

select * from "materias" where "materias"."id" in (1, 4, 7, 9, 10);

select * from "casos_clinicos" where "casos_clinicos"."id" in (9, 11, 13, 17, 38);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (3862, 3872, 3932, 4038, 4078);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (3474, 3483, 3539, 3636, 3674) order by "salas"."nombre" asc;
