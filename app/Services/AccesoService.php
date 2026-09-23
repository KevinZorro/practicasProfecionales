<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoUsuario;
use App\Models\User;

/**
 * Quién puede entrar a la plataforma (regla 8 del CLAUDE.md).
 *
 * El acceso depende de la vigencia institucional, no del correo: los
 * egresados conservan su cuenta institucional, así que tener el correo no
 * basta. La vigencia la lleva users.estado, que actualizará la
 * sincronización institucional y nunca se toca a mano.
 *
 * Es el único sitio donde se decide. Lo consultan la entrada —hoy el acceso
 * de desarrollo, mañana el controlador de Google (RF18)— y el middleware
 * VerificarUsuarioActivo, que corta a quien se desactiva con la sesión
 * abierta. Si la regla crece (por ejemplo, exigir además algún rol
 * vigente), crece aquí y las dos puertas la heredan.
 */
final class AccesoService
{
    public function puedeEntrar(User $usuario): bool
    {
        return $usuario->estado === EstadoUsuario::Activo;
    }

    /** El mensaje que ve quien no puede entrar. Uno solo para las dos puertas. */
    public function motivoDelRechazo(): string
    {
        return 'Tu vinculación con la institución no está vigente, así que no puedes entrar a la plataforma. '
            .'Si crees que es un error, comunícate con el laboratorio.';
    }
}
