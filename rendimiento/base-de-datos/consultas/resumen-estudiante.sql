-- resumen-estudiante: Pantalla «Hoy» del panel (todas las consultas de un bloque por permiso).
-- Origen: app/Services/ResumenDelDiaService.php:46 para()
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set uid random(162, 1161)

select * from "users" where "users"."id" = :uid limit 1;

select * from "evaluacion_estudiantes" where "estudiante_id" = :uid and exists (select * from "evaluaciones" where "evaluacion_estudiantes"."evaluacion_id" = "evaluaciones"."id" and "estado" = 'finalizada') order by "id" desc;

select * from "evaluaciones" where "evaluaciones"."id" in (74, 145, 163, 258, 306, 456, 566, 671, 731, 771, 937, 971, 1007, 1026, 1079, 1091, 1093);

select * from "tipos_evaluacion" where "tipos_evaluacion"."id" in (1, 3, 5, 6, 7, 8);

select * from "users" where "users"."id" in (2, 13, 30, 36, 42, 56, 57, 64, 65, 74, 77, 90, 94, 126, 138, 154, 155);

select * from "solicitudes" where "solicitudes"."id" in (301, 532, 594, 920, 1095, 1654, 2050, 2444, 2686, 2814, 3413, 3535, 3679, 3741, 3923, 3965, 3978);

select * from "materias" where "materias"."id" in (1, 3, 5, 8, 9, 10, 11, 12);

select * from "evaluacion_items" where "evaluacion_items"."evaluacion_id" in (74, 145, 163, 258, 306, 456, 566, 671, 731, 771, 937, 971, 1007, 1026, 1079, 1091, 1093) order by "orden" asc;

select "evaluacion_items".*, "evaluacion_estudiante_item"."evaluacion_estudiante_id" as "pivot_evaluacion_estudiante_id", "evaluacion_estudiante_item"."evaluacion_item_id" as "pivot_evaluacion_item_id", "evaluacion_estudiante_item"."cumplido" as "pivot_cumplido" from "evaluacion_items" inner join "evaluacion_estudiante_item" on "evaluacion_items"."id" = "evaluacion_estudiante_item"."evaluacion_item_id" where "evaluacion_estudiante_item"."evaluacion_estudiante_id" in (864, 1675, 1880, 2995, 3532, 5235, 6515, 7725, 8399, 8856, 10795, 11193, 11591, 11810, 12374, 12521, 12536);

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select * from "formatos_confidencialidad" where "firmante_id" = :uid and "periodo_academico" = '2026-2' limit 1;
