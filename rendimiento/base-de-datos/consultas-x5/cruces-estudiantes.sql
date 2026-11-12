-- cruces-estudiantes: Comprobación de que ninguno de 15 estudiantes está en otra sesión que se cruce (al armar o completar la lista).
-- Origen: app/Services/CrucesDeSesionService.php:59 garantizarEstudiantesLibres
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set off random(-440, 40)
\set h random(0, 10)
\set e1 random(762, 5761)
\set e2 random(762, 5761)
\set e3 random(762, 5761)
\set e4 random(762, 5761)
\set e5 random(762, 5761)
\set e6 random(762, 5761)
\set e7 random(762, 5761)
\set e8 random(762, 5761)
\set e9 random(762, 5761)
\set e10 random(762, 5761)
\set e11 random(762, 5761)
\set e12 random(762, 5761)
\set e13 random(762, 5761)
\set e14 random(762, 5761)
\set e15 random(762, 5761)

select * from "solicitudes" where "estado" in ('pendiente', 'revisada', 'aprobada') and "solicitudes"."fecha"::date = (CURRENT_DATE + :off) and "solicitudes"."hora_inicio" < (TIME '07:00' + (:h + 2) * INTERVAL '1 hour') and "solicitudes"."hora_fin" > (TIME '07:00' + :h * INTERVAL '1 hour') and exists (select * from "users" inner join "estudiante_solicitud" on "users"."id" = "estudiante_solicitud"."estudiante_id" where "solicitudes"."id" = "estudiante_solicitud"."solicitud_id" and "users"."id" in (:e1, :e2, :e3, :e4, :e5, :e6, :e7, :e8, :e9, :e10, :e11, :e12, :e13, :e14, :e15) and "estudiante_solicitud"."retirado_at" is null) order by "hora_inicio" asc;
