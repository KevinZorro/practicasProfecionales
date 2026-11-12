-- calendario-docente: Calendario de un mes (solo las suyas o las que dicta).
-- Origen: app/Services/SolicitudService.php:593 paraCalendario
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set off random(-440, 10)
\set uid random(2, 761)

select * from "solicitudes" where "estado" = 'aprobada' and "fecha" between (CURRENT_DATE + :off) and (CURRENT_DATE + :off + 30) and ("docente_id" = :uid or "docente_que_dicta_id" = :uid) order by "fecha" asc, "hora_inicio" asc, "id" asc;

select "id", "nombre" from "users" where "users"."id" in (52);

select * from "materias" where "materias"."id" in (2, 3, 6, 9, 10, 12);

select * from "casos_clinicos" where "casos_clinicos"."id" in (4, 10, 14, 21, 22, 24, 25);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (18124, 18520, 18540, 18793, 18864, 18904, 18912);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (13100, 13388, 13400, 13588, 13644, 13669, 13676) order by "salas"."nombre" asc;
