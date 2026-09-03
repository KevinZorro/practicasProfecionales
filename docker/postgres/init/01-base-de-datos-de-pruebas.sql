-- Se ejecuta una sola vez, cuando el volumen de PostgreSQL se crea vacío.
-- Crea la base que usa "php artisan test" (ver phpunit.xml) para no tocar
-- los datos de desarrollo al ejecutar la suite.
CREATE DATABASE laboratorio_simulacion_testing;
