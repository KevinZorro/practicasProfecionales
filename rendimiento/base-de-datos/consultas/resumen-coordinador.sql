-- resumen-coordinador: Pantalla «Hoy» del panel (todas las consultas de un bloque por permiso).
-- Origen: app/Services/ResumenDelDiaService.php:46 para()
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set uid random(2, 161)

select * from "users" where "users"."id" = :uid limit 1;

select count(*) as aggregate from "solicitudes" where "estado" = 'revisada' and ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE);

select * from "solicitudes" where "estado" = 'revisada' and ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE) order by "fecha" asc, "hora_inicio" asc limit 5 offset 0;

select "id", "nombre" from "users" where "users"."id" in (40, 51, 84, 122, 138);

select "id", "nombre" from "materias" where "materias"."id" in (2, 6, 12);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (24, 26, 30, 38);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (4092, 4094, 4095, 4115, 4116);

select * from "preparaciones" where exists (select * from "solicitudes" where "preparaciones"."solicitud_id" = "solicitudes"."id" and "fecha"::date = CURRENT_DATE) and exists (select * from "solicitudes" where "preparaciones"."solicitud_id" = "solicitudes"."id" and "estado" = 'aprobada') order by (select "hora_inicio" from "solicitudes" where "solicitudes"."id" = "preparaciones"."solicitud_id") asc;

select * from "solicitudes" where "solicitudes"."id" in (4097, 4101, 4102, 4105);

select * from "users" where "users"."id" in (19, 20, 74, 161);

select * from "materias" where "materias"."id" in (1, 4, 8, 12);

select * from "casos_clinicos" where "casos_clinicos"."id" in (12, 32, 35, 39);

select "users"."id", "estudiante_solicitud"."solicitud_id" as "pivot_solicitud_id", "estudiante_solicitud"."estudiante_id" as "pivot_estudiante_id", "estudiante_solicitud"."retirado_at" as "pivot_retirado_at", "estudiante_solicitud"."retirado_por" as "pivot_retirado_por", "estudiante_solicitud"."motivo_retiro" as "pivot_motivo_retiro", "estudiante_solicitud"."created_at" as "pivot_created_at", "estudiante_solicitud"."updated_at" as "pivot_updated_at" from "users" inner join "estudiante_solicitud" on "users"."id" = "estudiante_solicitud"."estudiante_id" where "estudiante_solicitud"."retirado_at" is null and "estudiante_solicitud"."solicitud_id" in (4097, 4101, 4102, 4105);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (3687, 3688, 3689, 3690) order by "salas"."nombre" asc;

select "items_inventario".*, "preparacion_item"."preparacion_id" as "pivot_preparacion_id", "preparacion_item"."item_inventario_id" as "pivot_item_inventario_id", "preparacion_item"."cantidad" as "pivot_cantidad", "preparacion_item"."alistado" as "pivot_alistado" from "items_inventario" inner join "preparacion_item" on "items_inventario"."id" = "preparacion_item"."item_inventario_id" where "preparacion_item"."preparacion_id" in (3687, 3688, 3689, 3690);

select count(*) as aggregate from "solicitudes" where "estado" = 'pendiente' and ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE);

select * from "solicitudes" where "estado" = 'pendiente' and ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE) order by "fecha" asc, "hora_inicio" asc limit 5 offset 0;

select "id", "nombre" from "users" where "users"."id" in (18, 46, 107, 130, 159);

select "id", "nombre" from "materias" where "materias"."id" in (4, 5, 10, 11, 12);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (16, 22, 29, 31, 35);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (4093, 4096, 4098, 4099, 4100);

select * from "solicitudes" where COALESCE(docente_que_dicta_id, docente_id) = :uid and "estado" = 'aprobada' and "fecha"::date >= CURRENT_DATE order by "fecha" asc, "hora_inicio" asc limit 5;

select count(*) as aggregate from "solicitudes" where "docente_id" = :uid and "estado" in ('pendiente', 'revisada');

select * from "solicitudes" where "docente_id" = :uid and "estado" in ('pendiente', 'revisada') order by "fecha" asc, "hora_inicio" asc limit 5 offset 0;

select "id", "nombre" from "users" where "users"."id" in (2);

select "id", "nombre" from "materias" where "materias"."id" in (4);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (16);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (4465);

select * from "solicitudes" where COALESCE(docente_que_dicta_id, docente_id) = :uid and "tipo" = 'evaluacion' and "estado" = 'aprobada' and not exists (select * from "evaluaciones" where "solicitudes"."id" = "evaluaciones"."solicitud_id") order by "fecha" asc, "hora_inicio" asc;

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select count(*) as aggregate from "formatos_confidencialidad" where "periodo_academico" = '2026-2' and "estado" = 'cargado';

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select * from "formatos_confidencialidad" where "firmante_id" = :uid and "periodo_academico" = '2026-2' limit 1;
