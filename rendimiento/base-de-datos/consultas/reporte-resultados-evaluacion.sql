-- reporte-resultados-evaluacion: Resultados de evaluaciones finalizadas por estudiante, primera página (RF55).
-- Origen: app/Services/ReporteService.php:98 resultadosDeEvaluacion
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set off random(-440, -30)
\set pagina random(0, 40) * 15

select count(*) as aggregate from "evaluacion_estudiantes" where exists (select * from "evaluaciones" where "evaluacion_estudiantes"."evaluacion_id" = "evaluaciones"."id" and "estado" = 'finalizada' and exists (select * from "solicitudes" where "evaluaciones"."solicitud_id" = "solicitudes"."id" and "fecha"::date >= (CURRENT_DATE + :off) and "fecha"::date <= (CURRENT_DATE + :off + 60)));

select * from "evaluacion_estudiantes" where exists (select * from "evaluaciones" where "evaluacion_estudiantes"."evaluacion_id" = "evaluaciones"."id" and "estado" = 'finalizada' and exists (select * from "solicitudes" where "evaluaciones"."solicitud_id" = "solicitudes"."id" and "fecha"::date >= (CURRENT_DATE + :off) and "fecha"::date <= (CURRENT_DATE + :off + 60))) order by "evaluacion_id" asc, "estudiante_id" asc, "intento" asc limit 25 offset :pagina;

select "id", "nombre", "email" from "users" where "users"."id" in (162, 185, 204, 225, 232, 236, 301, 336, 381, 387, 399, 401, 439, 460, 470, 526, 534, 599, 615, 626, 637, 646, 735, 747, 836);

select "id", "solicitud_id", "tipo_evaluacion_id", "docente_id" from "evaluaciones" where "evaluaciones"."id" in (1061, 1062);

select "id", "nombre" from "users" where "users"."id" in (43, 137);

select "id", "nombre" from "tipos_evaluacion" where "tipos_evaluacion"."id" in (2, 3);

select "id", "materia_id", "fecha" from "solicitudes" where "solicitudes"."id" in (3866, 3870);

select "id", "codigo", "nombre", "semestre" from "materias" where "materias"."id" in (6, 7);
