-- bitacora-filtrada: Bitácora filtrada por acción y rango de fechas.
-- Origen: app/Services/BitacoraService.php:77 listado
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set off random(-440, 10)
\set pagina random(0, 10) * 25

select count(*) as aggregate from "bitacora" where "accion" = 'solicitud_aprobada' and "created_at"::date >= (CURRENT_DATE + :off) and "created_at"::date <= (CURRENT_DATE + :off + 30);

select * from "bitacora" where "accion" = 'solicitud_aprobada' and "created_at"::date >= (CURRENT_DATE + :off) and "created_at"::date <= (CURRENT_DATE + :off + 30) order by "created_at" desc, "id" desc limit 25 offset :pagina;

select "id", "nombre" from "users" where "users"."id" in (2, 3);
