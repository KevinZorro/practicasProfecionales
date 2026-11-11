-- resumen-supervisor: Pantalla «Hoy» del panel (todas las consultas de un bloque por permiso).
-- Origen: app/Services/ResumenDelDiaService.php:46 para()
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set uid random(2, 161)

select * from "users" where "users"."id" = :uid limit 1;

select * from "solicitudes" where COALESCE(docente_que_dicta_id, docente_id) = :uid and "estado" = 'aprobada' and "fecha"::date >= CURRENT_DATE order by "fecha" asc, "hora_inicio" asc limit 5;

select "id", "nombre" from "users" where "users"."id" in (22);

select "id", "nombre" from "materias" where "materias"."id" in (6, 8);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (13, 39);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (4158, 4381);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (3719, 3819) order by "salas"."nombre" asc;

select count(*) as aggregate from "solicitudes" where "docente_id" = :uid and "estado" in ('pendiente', 'revisada');

select * from "solicitudes" where "docente_id" = :uid and "estado" in ('pendiente', 'revisada') order by "fecha" asc, "hora_inicio" asc limit 5 offset 0;

select "id", "nombre" from "users" where "users"."id" in (22);

select "id", "nombre" from "materias" where "materias"."id" in (9);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (33, 40);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (422, 4153);

select * from "solicitudes" where COALESCE(docente_que_dicta_id, docente_id) = :uid and "tipo" = 'evaluacion' and "estado" = 'aprobada' and not exists (select * from "evaluaciones" where "solicitudes"."id" = "evaluaciones"."solicitud_id") order by "fecha" asc, "hora_inicio" asc;

select * from "materias" where "materias"."id" in (6);

select * from "casos_clinicos" where "casos_clinicos"."id" in (13);

select "id", "nombre" from "users" where "users"."id" in (22);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (4158);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (3719) order by "salas"."nombre" asc;

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select * from "formatos_confidencialidad" where "firmante_id" = :uid and "periodo_academico" = '2026-2' limit 1;

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select "docente_id" from "docente_supervisor" where "supervisor_id" = :uid;

select "docente_id" from "docente_supervisor" where "supervisor_id" = :uid;

select count(*) as aggregate from "evaluaciones" where "estado" = 'finalizada' and "docente_id" in (32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43) and exists (select * from "solicitudes" where "evaluaciones"."solicitud_id" = "solicitudes"."id" and "fecha"::date >= '2026-08-03');
