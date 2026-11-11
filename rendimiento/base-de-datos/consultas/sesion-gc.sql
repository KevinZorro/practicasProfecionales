-- sesion-gc: Recolección de sesiones vencidas (lotería 2/100 de las peticiones).
-- Origen: Illuminate\Session\DatabaseSessionHandler::gc()
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.


delete from "sessions" where "last_activity" <= (extract(epoch from now())::int - 7200);
