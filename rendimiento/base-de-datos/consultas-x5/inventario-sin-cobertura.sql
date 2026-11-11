-- inventario-sin-cobertura: Aprobadas futuras que se quedaron sin unidades (RF66.2): una consulta con subconsultas.
-- Origen: app/Services/InventarioService.php:255 solicitudesAprobadasSinCobertura
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.


select "solicitudes"."id" from "solicitudes" where "estado" = 'aprobada' and "solicitudes"."fecha"::date >= CURRENT_DATE and exists (select * from "solicitud_item" inner join "items_inventario" on "items_inventario"."id" = "solicitud_item"."item_inventario_id" where "solicitud_item"."solicitud_id" = "solicitudes"."id" and items_inventario.cantidad_operativa < (
                    SELECT COALESCE(SUM(otras.cantidad), 0)
                    FROM solicitud_item AS otras
                    JOIN solicitudes AS s2 ON s2.id = otras.solicitud_id
                    WHERE otras.item_inventario_id = solicitud_item.item_inventario_id
                      AND s2.estado = 'aprobada'
                      AND s2.fecha = solicitudes.fecha
                      AND s2.hora_inicio < solicitudes.hora_fin
                      AND s2.hora_fin > solicitudes.hora_inicio
                ));
