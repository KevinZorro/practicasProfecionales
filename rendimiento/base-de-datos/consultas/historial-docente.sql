-- historial-docente: Mis solicitudes: historial del docente, paginado.
-- Origen: app/Services/SolicitudService.php:438 historialDelDocente
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set pagina random(0, 40) * 15
\set uid random(2, 161)

select count(*) as aggregate from "solicitudes" where ("docente_id" = :uid or "docente_que_dicta_id" = :uid);

select * from "solicitudes" where ("docente_id" = :uid or "docente_que_dicta_id" = :uid) order by "fecha" desc, "hora_inicio" desc limit 15 offset :pagina;

select * from "materias" where "materias"."id" in (1, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12);

select * from "casos_clinicos" where "casos_clinicos"."id" in (1, 3, 6, 9, 11, 12, 13, 17, 18, 32, 34, 36, 38, 40);

select * from "preparaciones" where "preparaciones"."solicitud_id" in (2455, 2510, 2539, 2559, 2792, 3039, 3097, 3499, 3712, 3831, 3862, 3872, 3932, 4038, 4078);

select "salas".*, "preparacion_sala"."preparacion_id" as "pivot_preparacion_id", "preparacion_sala"."sala_id" as "pivot_sala_id" from "salas" inner join "preparacion_sala" on "salas"."id" = "preparacion_sala"."sala_id" where "preparacion_sala"."preparacion_id" in (2215, 2261, 2286, 2304, 2737, 2790, 3141, 3338, 3447, 3474, 3483, 3539, 3636, 3674) order by "salas"."nombre" asc;

select "id", "nombre" from "users" where "users"."id" in (92);
