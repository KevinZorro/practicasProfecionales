-- resumen-estudiante: Pantalla «Hoy» del panel (todas las consultas de un bloque por permiso).
-- Origen: app/Services/ResumenDelDiaService.php:46 para()
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set uid random(762, 5761)

select * from "users" where "users"."id" = :uid limit 1;

select * from "evaluacion_estudiantes" where "estudiante_id" = :uid and exists (select * from "evaluaciones" where "evaluacion_estudiantes"."evaluacion_id" = "evaluaciones"."id" and "estado" = 'finalizada') order by "id" desc;

select * from "evaluaciones" where "evaluaciones"."id" in (243, 857, 1483, 1821, 1959, 2911, 3222, 3363, 3780, 3960, 3965);

select * from "tipos_evaluacion" where "tipos_evaluacion"."id" in (1, 2, 3, 4, 5, 6, 7);

select * from "users" where "users"."id" in (114, 144, 202, 279, 370, 422, 455, 539, 545, 605, 755);

select * from "solicitudes" where "solicitudes"."id" in (986, 3982, 6639, 8209, 8788, 13354, 14729, 15359, 17300, 18184, 18201);

select * from "materias" where "materias"."id" in (1, 2, 3, 5, 6, 8, 9, 10, 11);

select * from "evaluacion_items" where "evaluacion_items"."evaluacion_id" in (243, 857, 1483, 1821, 1959, 2911, 3222, 3363, 3780, 3960, 3965) order by "orden" asc;

select "evaluacion_items".*, "evaluacion_estudiante_item"."evaluacion_estudiante_id" as "pivot_evaluacion_estudiante_id", "evaluacion_estudiante_item"."evaluacion_item_id" as "pivot_evaluacion_item_id", "evaluacion_estudiante_item"."cumplido" as "pivot_cumplido" from "evaluacion_items" inner join "evaluacion_estudiante_item" on "evaluacion_items"."id" = "evaluacion_estudiante_item"."evaluacion_item_id" where "evaluacion_estudiante_item"."evaluacion_estudiante_id" in (2710, 9651, 16668, 20439, 22012, 32761, 36206, 37831, 42632, 44611, 44658);

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select * from "formatos_confidencialidad" where "firmante_id" = :uid and "periodo_academico" = '2026-2' limit 1;
