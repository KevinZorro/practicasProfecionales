-- calendario-estudiante: Calendario de un mes (solo donde está en la lista (whereHas)).
-- Origen: app/Services/SolicitudService.php:593 paraCalendario
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set off random(-440, 10)
\set uid random(162, 1161)

select * from "solicitudes" where "estado" = 'aprobada' and "fecha" between (CURRENT_DATE + :off) and (CURRENT_DATE + :off + 30) and exists (select * from "users" inner join "estudiante_solicitud" on "users"."id" = "estudiante_solicitud"."estudiante_id" where "solicitudes"."id" = "estudiante_solicitud"."solicitud_id" and "users"."id" = :uid and "estudiante_solicitud"."retirado_at" is null) order by "fecha" asc, "hora_inicio" asc, "id" asc;

select "id", "nombre" from "users" where "users"."id" in (21, 23, 26, 41, 49, 77, 79, 83, 97, 99, 114, 141, 154, 155);

select * from "materias" where "materias"."id" in (2, 3, 4, 5, 6, 7, 8, 9, 10, 12);

select * from "casos_clinicos" where "casos_clinicos"."id" in (2, 3, 4, 7, 14, 20, 21, 30, 34, 36, 38, 40);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (3912, 3923, 3935, 3965, 3978, 4008, 4028, 4041, 4079, 4084, 4124, 4134, 4161, 4165, 4191);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (3520, 3531, 3542, 3569, 3582, 3609, 3626, 3639, 3675, 3680, 3701, 3707, 3721, 3723, 3734) order by "salas"."nombre" asc;
