-- resumen-docente: Pantalla «Hoy» del panel (todas las consultas de un bloque por permiso).
-- Origen: app/Services/ResumenDelDiaService.php:46 para()
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set uid random(2, 161)

select * from "users" where "users"."id" = :uid limit 1;

select * from "solicitudes" where COALESCE(docente_que_dicta_id, docente_id) = :uid and "estado" = 'aprobada' and "fecha"::date >= CURRENT_DATE order by "fecha" asc, "hora_inicio" asc limit 5;

select count(*) as aggregate from "solicitudes" where "docente_id" = :uid and "estado" in ('pendiente', 'revisada');

select * from "solicitudes" where "docente_id" = :uid and "estado" in ('pendiente', 'revisada') order by "fecha" asc, "hora_inicio" asc limit 5 offset 0;

select "id", "nombre" from "users" where "users"."id" in (92);

select "id", "nombre" from "materias" where "materias"."id" in (3, 5, 7);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (3, 5, 26);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (28, 274, 2792);

select * from "solicitudes" where COALESCE(docente_que_dicta_id, docente_id) = :uid and "tipo" = 'evaluacion' and "estado" = 'aprobada' and not exists (select * from "evaluaciones" where "solicitudes"."id" = "evaluaciones"."solicitud_id") order by "fecha" asc, "hora_inicio" asc;

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select * from "formatos_confidencialidad" where "firmante_id" = :uid and "periodo_academico" = '2026-2' limit 1;
