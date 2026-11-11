-- participacion-lista: Quién puede entrar a la práctica en la lista de una sesión: formato al día y sin bloqueo (RF70), 15 personas.
-- Origen: app/Services/ParticipacionService.php:48 impedimentos
-- SQL capturado de Laravel con rendimiento/base-de-datos/capturar-consultas.php; no se edita a mano.
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

select * from "periodos_academicos" where "cerrado_at" is null limit 1;

select "firmante_id" from "formatos_confidencialidad" where "firmante_id" in (:e1, :e2, :e3, :e4, :e5, :e6, :e7, :e8, :e9, :e10, :e11, :e12, :e13, :e14, :e15) and "periodo_academico" = '2026-2' and ("estado" = 'verificado' or "recibido_fisico_at" is not null);

select * from "bloqueos" where "levantado_at" is null and "user_id" in (:e1, :e2, :e3, :e4, :e5, :e6, :e7, :e8, :e9, :e10, :e11, :e12, :e13, :e14, :e15);
