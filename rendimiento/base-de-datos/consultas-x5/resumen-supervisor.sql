-- resumen-supervisor: Pantalla «Hoy» del panel (todas las consultas de un bloque por permiso).
-- Origen: app/Services/ResumenDelDiaService.php:46 para()
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set uid random(2, 761)

select * from "users" where "users"."id" = :uid limit 1;

select * from "solicitudes" where COALESCE(docente_que_dicta_id, docente_id) = :uid and "estado" = 'aprobada' and "fecha"::date >= CURRENT_DATE order by "fecha" asc, "hora_inicio" asc limit 5;

select "id", "nombre" from "users" where "users"."id" in (22);

select "id", "nombre" from "materias" where "materias"."id" in (8, 9);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (15, 40);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (20757, 21410);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (14439, 14775) order by "salas"."nombre" asc;

select count(*) as aggregate from "solicitudes" where "docente_id" = :uid and "estado" in ('pendiente', 'revisada');

select * from "solicitudes" where "docente_id" = :uid and "estado" in ('pendiente', 'revisada') order by "fecha" asc, "hora_inicio" asc limit 5 offset 0;

select "id", "nombre" from "users" where "users"."id" in (22);

select "id", "nombre" from "materias" where "materias"."id" in (3, 8, 9, 12);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (4, 12, 27, 39);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (9994, 17761, 19087, 19411, 20115);

select * from "solicitudes" where COALESCE(docente_que_dicta_id, docente_id) = :uid and "tipo" = 'evaluacion' and "estado" = 'aprobada' and not exists (select * from "evaluaciones" where "solicitudes"."id" = "evaluaciones"."solicitud_id") order by "fecha" asc, "hora_inicio" asc;

select * from "materias" where "materias"."id" in (8);

select * from "casos_clinicos" where "casos_clinicos"."id" in (15);

select "id", "nombre" from "users" where "users"."id" in (22);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (20757);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (14439) order by "salas"."nombre" asc;

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select * from "formatos_confidencialidad" where "firmante_id" = :uid and "periodo_academico" = '2026-2' limit 1;

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select "docente_id" from "docente_supervisor" where "supervisor_id" = :uid;

select "docente_id" from "docente_supervisor" where "supervisor_id" = :uid;

select count(*) as aggregate from "evaluaciones" where "estado" = 'finalizada' and "docente_id" in (32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43) and exists (select * from "solicitudes" where "evaluaciones"."solicitud_id" = "solicitudes"."id" and "fecha"::date >= '2026-08-03');
