<?php

declare(strict_types=1);

namespace App\Services;

/** Lo que borró demo:preparar --limpiar, y lo que no pudo. */
final readonly class DemostracionLimpia
{
    /**
     * @param  list<string>  $cuentasDeshabilitadas  correos de las cuentas que quedaron en datos reales
     * @param  list<string>  $salasDesactivadas  códigos de las salas que usa una sesión real
     */
    public function __construct(
        public int $solicitudes,
        public int $cuentasBorradas,
        public array $cuentasDeshabilitadas,
        public array $salasDesactivadas,
    ) {}
}
