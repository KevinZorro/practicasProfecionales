<?php

declare(strict_types=1);

namespace App\Services;

/**
 * De dónde lee la sincronización a las personas de la universidad (RF20).
 *
 * Hay una sola implementación hoy, la simulada. Cuando la universidad
 * entregue el motor, el acceso de solo lectura y la estructura de sus
 * tablas, se escribe la real (una clase que lee de esa base y devuelve
 * PersonaInstitucional) y se registra en AppServiceProvider según
 * config('laboratorio.sincronizacion.fuente'). UsuarioSyncService no
 * cambia.
 */
interface FuenteInstitucional
{
    /**
     * Todas las personas que la fuente conoce, vigentes o no.
     *
     * @return list<PersonaInstitucional>
     */
    public function personas(): array;
}
