-- resumen-coordinador: Pantalla «Hoy» del panel (todas las consultas de un bloque por permiso).
-- Origen: app/Services/ResumenDelDiaService.php:46 para()
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set uid random(2, 761)

select * from "users" where "users"."id" = :uid limit 1;

select count(*) as aggregate from "solicitudes" where "estado" = 'revisada' and ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE);

select * from "solicitudes" where "estado" = 'revisada' and ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE) order by "fecha" asc, "hora_inicio" asc limit 5 offset 0;

select "id", "nombre" from "users" where "users"."id" in (14, 150, 422, 638, 714);

select "id", "nombre" from "materias" where "materias"."id" in (2, 4, 5, 8, 9);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (4, 8, 9, 14, 24);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (18930, 18931, 18938, 18939, 18940);

select * from "preparaciones" where exists (select * from "solicitudes" where "preparaciones"."solicitud_id" = "solicitudes"."id" and "fecha"::date = CURRENT_DATE) and exists (select * from "solicitudes" where "preparaciones"."solicitud_id" = "solicitudes"."id" and "estado" = 'aprobada') order by (select "hora_inicio" from "solicitudes" where "solicitudes"."id" = "preparaciones"."solicitud_id") asc;

select * from "solicitudes" where "solicitudes"."id" in (18932, 18933, 18934, 18942, 18943, 18944, 18946, 18948, 18956, 18957, 18958, 18964, 18965, 18967, 18973, 18974, 18976, 18979, 18986, 18988, 18990, 18993, 18994, 18998, 18999, 19000, 19008, 19012, 19015, 19016, 19017, 19018, 19020, 19021, 19026, 19028, 19029, 19037, 19038, 19042, 19046);

select * from "users" where "users"."id" in (2, 29, 46, 67, 68, 94, 96, 160, 166, 168, 177, 266, 279, 289, 314, 317, 335, 339, 360, 372, 401, 413, 447, 467, 474, 504, 519, 527, 548, 555, 566, 570, 622, 630, 663, 664, 683, 713, 727, 743, 755);

select * from "materias" where "materias"."id" in (1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);

select * from "casos_clinicos" where "casos_clinicos"."id" in (1, 4, 5, 6, 9, 10, 13, 14, 15, 16, 19, 22, 23, 24, 25, 27, 29, 32, 33, 34, 37, 38, 39, 40);

select "users"."id", "estudiante_solicitud"."solicitud_id" as "pivot_solicitud_id", "estudiante_solicitud"."estudiante_id" as "pivot_estudiante_id", "estudiante_solicitud"."retirado_at" as "pivot_retirado_at", "estudiante_solicitud"."retirado_por" as "pivot_retirado_por", "estudiante_solicitud"."motivo_retiro" as "pivot_motivo_retiro", "estudiante_solicitud"."created_at" as "pivot_created_at", "estudiante_solicitud"."updated_at" as "pivot_updated_at" from "users" inner join "estudiante_solicitud" on "users"."id" = "estudiante_solicitud"."estudiante_id" where "estudiante_solicitud"."retirado_at" is null and "estudiante_solicitud"."solicitud_id" in (18932, 18933, 18934, 18942, 18943, 18944, 18946, 18948, 18956, 18957, 18958, 18964, 18965, 18967, 18973, 18974, 18976, 18979, 18986, 18988, 18990, 18993, 18994, 18998, 18999, 19000, 19008, 19012, 19015, 19016, 19017, 19018, 19020, 19021, 19026, 19028, 19029, 19037, 19038, 19042, 19046);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (13689, 13690, 13691, 13692, 13693, 13694, 13695, 13696, 13697, 13698, 13699, 13700, 13701, 13702, 13703, 13704, 13705, 13706, 13707, 13708, 13709, 13710, 13711, 13712, 13713, 13714, 13715, 13716, 13717, 13718, 13719, 13720, 13721, 13722, 13723, 13724, 13725, 13726, 13727, 13728, 13729) order by "salas"."nombre" asc;

select "items_inventario".*, "preparacion_item"."preparacion_id" as "pivot_preparacion_id", "preparacion_item"."item_inventario_id" as "pivot_item_inventario_id", "preparacion_item"."cantidad" as "pivot_cantidad", "preparacion_item"."alistado" as "pivot_alistado" from "items_inventario" inner join "preparacion_item" on "items_inventario"."id" = "preparacion_item"."item_inventario_id" where "preparacion_item"."preparacion_id" in (13689, 13690, 13691, 13692, 13693, 13694, 13695, 13696, 13697, 13698, 13699, 13700, 13701, 13702, 13703, 13704, 13705, 13706, 13707, 13708, 13709, 13710, 13711, 13712, 13713, 13714, 13715, 13716, 13717, 13718, 13719, 13720, 13721, 13722, 13723, 13724, 13725, 13726, 13727, 13728, 13729);

select count(*) as aggregate from "solicitudes" where "estado" = 'pendiente' and ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE);

select * from "solicitudes" where "estado" = 'pendiente' and ("solicitudes"."estado" not in ('pendiente', 'revisada') or "solicitudes"."fecha"::date >= CURRENT_DATE) order by "fecha" asc, "hora_inicio" asc limit 5 offset 0;

select "id", "nombre" from "users" where "users"."id" in (132, 133, 368, 540, 595);

select "id", "nombre" from "materias" where "materias"."id" in (1, 3, 8, 11);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (1, 20, 27, 34, 35);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (18929, 18935, 18936, 18937, 18945);

select * from "solicitudes" where COALESCE(docente_que_dicta_id, docente_id) = :uid and "estado" = 'aprobada' and "fecha"::date >= CURRENT_DATE order by "fecha" asc, "hora_inicio" asc limit 5;

select "id", "nombre" from "users" where "users"."id" in (2);

select "id", "nombre" from "materias" where "materias"."id" in (2, 8);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (9, 39);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (18946, 19421);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (13695, 13849) order by "salas"."nombre" asc;

select count(*) as aggregate from "solicitudes" where "docente_id" = :uid and "estado" in ('pendiente', 'revisada');

select * from "solicitudes" where "docente_id" = :uid and "estado" in ('pendiente', 'revisada') order by "fecha" asc, "hora_inicio" asc limit 5 offset 0;

select "id", "nombre" from "users" where "users"."id" in (2);

select "id", "nombre" from "materias" where "materias"."id" in (3, 4, 5, 12);

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (7, 10, 16, 17);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (7982, 14081, 19033, 22326);

select * from "solicitudes" where COALESCE(docente_que_dicta_id, docente_id) = :uid and "tipo" = 'evaluacion' and "estado" = 'aprobada' and not exists (select * from "evaluaciones" where "solicitudes"."id" = "evaluaciones"."solicitud_id") order by "fecha" asc, "hora_inicio" asc;

select * from "materias" where "materias"."id" in (2);

select * from "casos_clinicos" where "casos_clinicos"."id" in (9);

select "id", "nombre" from "users" where "users"."id" in (2);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (19421);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (13849) order by "salas"."nombre" asc;

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select count(*) as aggregate from "formatos_confidencialidad" where "periodo_academico" = '2026-2' and "estado" = 'cargado';

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select * from "formatos_confidencialidad" where "firmante_id" = :uid and "periodo_academico" = '2026-2' limit 1;
