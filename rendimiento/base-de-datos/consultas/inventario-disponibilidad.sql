-- inventario-disponibilidad: Disponibilidad de los ítems de un escenario en una franja (operativas menos comprometidas).
-- Origen: app/Services/InventarioService.php:227 disponibilidadDeVarios
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
\set off random(-440, 40)
\set h random(0, 10)

select * from "items_inventario" where "id" in (32, 63, 94, 125, 39, 70);

select solicitud_item.item_inventario_id as item_id, SUM(solicitud_item.cantidad) as total from "solicitudes" inner join "solicitud_item" on "solicitud_item"."solicitud_id" = "solicitudes"."id" where "estado" = 'aprobada' and "solicitudes"."fecha"::date = (CURRENT_DATE + :off) and "solicitudes"."hora_inicio" < (TIME '07:00' + (:h + 2) * INTERVAL '1 hour') and "solicitudes"."hora_fin" > (TIME '07:00' + :h * INTERVAL '1 hour') and "solicitud_item"."item_inventario_id" in (32, 39, 63, 70, 94, 125) group by "solicitud_item"."item_inventario_id";
