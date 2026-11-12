-- historial-docente: Mis solicitudes: historial del docente, paginado.
-- Origen: app/Services/SolicitudService.php:438 historialDelDocente
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set pagina random(0, 40) * 15
\set uid random(2, 761)

select count(*) as aggregate from "solicitudes" where ("docente_id" = :uid or "docente_que_dicta_id" = :uid);

select * from "solicitudes" where ("docente_id" = :uid or "docente_que_dicta_id" = :uid) order by "fecha" desc, "hora_inicio" desc limit 15 offset :pagina;

select * from "materias" where "materias"."id" in (1, 2, 3, 5, 6, 9, 10, 12);

select * from "casos_clinicos" where "casos_clinicos"."id" in (3, 4, 6, 10, 14, 17, 21, 22, 24, 25, 26, 29, 34, 36, 37);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (16554, 17197, 18124, 18520, 18540, 18793, 18864, 18904, 18912, 19312, 20845, 21009, 21437, 21557, 21655);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (12432, 13100, 13388, 13400, 13588, 13644, 13669, 13676, 14569, 14793) order by "salas"."nombre" asc;

select "id", "nombre" from "users" where "users"."id" in (52);
