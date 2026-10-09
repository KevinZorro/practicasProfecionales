<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EstadoUsuario;
use App\Exceptions\AccesoRechazado;
use App\Models\User;

/**
 * Quién puede entrar a la plataforma (regla 8 del CLAUDE.md).
 *
 * El acceso depende de la vigencia institucional, no del correo: los
 * egresados conservan su cuenta institucional, así que tener el correo no
 * basta. La vigencia la lleva users.estado, que actualizará la
 * sincronización institucional y nunca se toca a mano.
 *
 * Es el único sitio donde se decide. Lo consultan la entrada —Google, y en
 * local el acceso de desarrollo— y el middleware VerificarUsuarioActivo, que
 * corta a quien se desactiva con la sesión abierta. Si la regla crece (por
 * ejemplo, exigir además algún rol vigente), crece aquí y las dos puertas la
 * heredan.
 */
final class AccesoService
{
    /**
     * Vigente en la institución y sin deshabilitar a mano por el ADMIN
     * (RF22, D6). Son dos marcas distintas: la primera la pone la
     * sincronización y la segunda no la toca nadie más que el ADMIN.
     */
    public function puedeEntrar(User $usuario): bool
    {
        return $usuario->estado === EstadoUsuario::Activo && ! $usuario->estaDeshabilitado();
    }

    /**
     * El mensaje que ve quien no puede entrar, el mismo en las dos puertas.
     * Sin cuenta a la vista, el de la vigencia institucional.
     */
    public function motivoDelRechazo(?User $usuario = null): string
    {
        if ($usuario?->estaDeshabilitado()) {
            return 'Tu cuenta está deshabilitada en la plataforma, así que no puedes entrar. '
                .'Si crees que es un error, comunícate con el laboratorio.';
        }

        return 'Tu vinculación con la institución no está vigente, así que no puedes entrar a la plataforma. '
            .'Si crees que es un error, comunícate con el laboratorio.';
    }

    /** Sin credenciales de Google no hay entrada con Google (RF18). */
    public function entradaConGoogleDisponible(): bool
    {
        return filled(config('services.google.client_id'));
    }

    /** El dominio de las cuentas institucionales, o null si no se configuró. */
    public function dominioInstitucional(): ?string
    {
        $dominio = config('services.google.dominio');

        return filled($dominio) ? mb_strtolower((string) $dominio) : null;
    }

    /**
     * La cuenta de la plataforma de quien volvió de Google (RF18).
     *
     * Solo entra quien ya tiene cuenta: la plataforma no crea usuarios al
     * vuelo, porque tener un correo institucional no autoriza el ingreso
     * (regla 8). Las cuentas las crea la sincronización institucional o el
     * laboratorio.
     *
     * La primera vez la cuenta se encuentra por el correo y queda vinculada
     * al identificador de Google; desde entonces se encuentra por ese
     * identificador, que no cambia aunque la persona cambie de correo.
     *
     * @throws AccesoRechazado
     */
    public function cuentaDeGoogle(IdentidadDeGoogle $identidad): User
    {
        // Sin correo verificado, cualquiera podría crear una cuenta de Google
        // con el correo de otra persona y entrar como ella.
        if (! $identidad->emailVerificado) {
            throw AccesoRechazado::correoSinVerificar();
        }

        $dominio = $this->dominioInstitucional();

        if ($dominio !== null && mb_strtolower((string) $identidad->dominio) !== $dominio) {
            throw AccesoRechazado::fueraDelDominio($dominio);
        }

        $usuario = User::query()->where('google_id', $identidad->id)->first()
            ?? User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($identidad->email)])->first();

        if ($usuario === null) {
            throw AccesoRechazado::sinCuenta();
        }

        // La cuenta ya se vinculó a otra identidad de Google. Dejar entrar a
        // esta por coincidir el correo sería entregársela a quien lo tenga
        // ahora.
        if ($usuario->google_id !== null && $usuario->google_id !== $identidad->id) {
            throw AccesoRechazado::vinculadaAOtraCuenta();
        }

        if (! $this->puedeEntrar($usuario)) {
            throw AccesoRechazado::inactivo($this->motivoDelRechazo($usuario));
        }

        if ($usuario->google_id === null) {
            $usuario->forceFill(['google_id' => $identidad->id])->save();
        }

        return $usuario;
    }
}
