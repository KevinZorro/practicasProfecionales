-- reporte-resultados-evaluacion: Resultados de evaluaciones finalizadas por estudiante, primera página (RF55).
-- Origen: app/Services/ReporteService.php:98 resultadosDeEvaluacion
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set off random(-440, -30)
\set pagina random(0, 40) * 15

select count(*) as aggregate from "evaluacion_estudiantes" where exists (select * from "evaluaciones" where "evaluacion_estudiantes"."evaluacion_id" = "evaluaciones"."id" and "estado" = 'finalizada' and exists (select * from "solicitudes" where "evaluaciones"."solicitud_id" = "solicitudes"."id" and "fecha"::date >= (CURRENT_DATE + :off) and "fecha"::date <= (CURRENT_DATE + :off + 60)));

select * from "evaluacion_estudiantes" where exists (select * from "evaluaciones" where "evaluacion_estudiantes"."evaluacion_id" = "evaluaciones"."id" and "estado" = 'finalizada' and exists (select * from "solicitudes" where "evaluaciones"."solicitud_id" = "solicitudes"."id" and "fecha"::date >= (CURRENT_DATE + :off) and "fecha"::date <= (CURRENT_DATE + :off + 60))) order by "evaluacion_id" asc, "estudiante_id" asc, "intento" asc limit 25 offset :pagina;

select "id", "nombre", "email" from "users" where "users"."id" in (807, 842, 1011, 1014, 1121, 1220, 1298, 1640, 1663, 1793, 2011, 2203, 2281, 2343, 2375, 2636, 2830, 2862, 2939, 3013, 3235, 3559, 3725, 3844, 3953);

select "id", "solicitud_id", "tipo_evaluacion_id", "docente_id" from "evaluaciones" where "evaluaciones"."id" in (3951, 3952, 3953);

select "id", "nombre" from "users" where "users"."id" in (103, 109, 313);

select "id", "nombre" from "tipos_evaluacion" where "tipos_evaluacion"."id" in (5, 7, 8);

select "id", "materia_id", "fecha" from "solicitudes" where "solicitudes"."id" in (18123, 18140, 18147);

select "id", "codigo", "nombre", "semestre" from "materias" where "materias"."id" in (7, 8, 9);
