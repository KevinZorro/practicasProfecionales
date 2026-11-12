-- cruces-docente: Comprobación de que el docente no dicta otra sesión a la vez (al solicitar, reprogramar o sustituir).
-- Origen: app/Services/CrucesDeSesionService.php:44 garantizarDocenteLibre
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set off random(-440, 40)
\set h random(0, 10)
\set uid random(2, 161)

select * from "solicitudes" where "estado" in ('pendiente', 'revisada', 'aprobada') and "solicitudes"."fecha"::date = (CURRENT_DATE + :off) and "solicitudes"."hora_inicio" < (TIME '07:00' + (:h + 2) * INTERVAL '1 hour') and "solicitudes"."hora_fin" > (TIME '07:00' + :h * INTERVAL '1 hour') and COALESCE(docente_que_dicta_id, docente_id) = :uid limit 1;
