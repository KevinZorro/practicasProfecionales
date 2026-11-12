-- cruces-estudiantes: Comprobación de que ninguno de 15 estudiantes está en otra sesión que se cruce (al armar o completar la lista).
-- Origen: app/Services/CrucesDeSesionService.php:59 garantizarEstudiantesLibres
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set off random(-440, 40)
\set h random(0, 10)
\set e1 random(162, 1161)
\set e2 random(162, 1161)
\set e3 random(162, 1161)
\set e4 random(162, 1161)
\set e5 random(162, 1161)
\set e6 random(162, 1161)
\set e7 random(162, 1161)
\set e8 random(162, 1161)
\set e9 random(162, 1161)
\set e10 random(162, 1161)
\set e11 random(162, 1161)
\set e12 random(162, 1161)
\set e13 random(162, 1161)
\set e14 random(162, 1161)
\set e15 random(162, 1161)

select * from "solicitudes" where "estado" in ('pendiente', 'revisada', 'aprobada') and "solicitudes"."fecha"::date = (CURRENT_DATE + :off) and "solicitudes"."hora_inicio" < (TIME '07:00' + (:h + 2) * INTERVAL '1 hour') and "solicitudes"."hora_fin" > (TIME '07:00' + :h * INTERVAL '1 hour') and exists (select * from "users" inner join "estudiante_solicitud" on "users"."id" = "estudiante_solicitud"."estudiante_id" where "solicitudes"."id" = "estudiante_solicitud"."solicitud_id" and "users"."id" in (:e1, :e2, :e3, :e4, :e5, :e6, :e7, :e8, :e9, :e10, :e11, :e12, :e13, :e14, :e15) and "estudiante_solicitud"."retirado_at" is null) order by "hora_inicio" asc;

select "id", "nombre" from "casos_clinicos" where "casos_clinicos"."id" in (34);

select "users".*, "estudiante_solicitud"."solicitud_id" as "pivot_solicitud_id", "estudiante_solicitud"."estudiante_id" as "pivot_estudiante_id", "estudiante_solicitud"."retirado_at" as "pivot_retirado_at", "estudiante_solicitud"."retirado_por" as "pivot_retirado_por", "estudiante_solicitud"."motivo_retiro" as "pivot_motivo_retiro", "estudiante_solicitud"."created_at" as "pivot_created_at", "estudiante_solicitud"."updated_at" as "pivot_updated_at" from "users" inner join "estudiante_solicitud" on "users"."id" = "estudiante_solicitud"."estudiante_id" where "estudiante_solicitud"."retirado_at" is null and "estudiante_solicitud"."solicitud_id" in (4134) and "users"."id" in (:e1, :e2, :e3, :e4, :e5, :e6, :e7, :e8, :e9, :e10, :e11, :e12, :e13, :e14, :e15);
