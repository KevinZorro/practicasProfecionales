-- resumen-docente: Pantalla «Hoy» del panel (todas las consultas de un bloque por permiso).
-- Origen: app/Services/ResumenDelDiaService.php:46 para()
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set uid random(2, 761)

select * from "users" where "users"."id" = :uid limit 1;

select * from "solicitudes" where COALESCE(docente_que_dicta_id, docente_id) = :uid and "estado" = 'aprobada' and "fecha"::date >= CURRENT_DATE order by "fecha" asc, "hora_inicio" asc limit 5;

select "id", "nombre" from "users" where "users"."id" in (52);

select "id", "nombre" from "materias" where "materias"."id" in (3, 5);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (3, 36);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (21009, 21437);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (14569, 14793) order by "salas"."nombre" asc;

select count(*) as aggregate from "solicitudes" where "docente_id" = :uid and "estado" in ('pendiente', 'revisada');

select * from "solicitudes" where "docente_id" = :uid and "estado" in ('pendiente', 'revisada') order by "fecha" asc, "hora_inicio" asc limit 5 offset 0;

select "id", "nombre" from "users" where "users"."id" in (52);

select "id", "nombre" from "materias" where "materias"."id" in (3, 6, 10, 11, 12);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (6, 7, 18, 29, 34);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (6587, 7916, 19312, 20845, 21557);

select * from "solicitudes" where COALESCE(docente_que_dicta_id, docente_id) = :uid and "tipo" = 'evaluacion' and "estado" = 'aprobada' and not exists (select * from "evaluaciones" where "solicitudes"."id" = "evaluaciones"."solicitud_id") order by "fecha" asc, "hora_inicio" asc;

select * from "materias" where "materias"."id" in (5);

select * from "casos_clinicos" where "casos_clinicos"."id" in (36);

select "id", "nombre" from "users" where "users"."id" in (52);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (21009);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (14569) order by "salas"."nombre" asc;

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select * from "formatos_confidencialidad" where "firmante_id" = :uid and "periodo_academico" = '2026-2' limit 1;
