<?php

use App\Exceptions\UsuarioDeLaBaseInvalido;
use App\Exceptions\UsuarioInvalido;
use App\Services\AccesoService;
use App\Services\RegistroPrevioService;
use App\Services\UsuarioDeLaAplicacionService;
use App\Services\UsuarioService;
use App\Services\UsuarioSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Aviso diario de sesiones próximas sin formato intramural (RF60). Corre
 * por el servicio "programador" del compose (schedule:work). A las 6 de la
 * mañana, en la zona de la aplicación, antes de que abra el laboratorio.
 */
Artisan::command('sesiones:avisar-formato-intramural', function (RegistroPrevioService $registroPrevio): void {
    $enviados = $registroPrevio->avisarSinFormatoIntramural();
    $this->info(sprintf('Avisos en cola: %d.', $enviados));
})->purpose('Avisa por correo de las sesiones próximas sin formato intramural (RF60)');

Schedule::command('sesiones:avisar-formato-intramural')
    ->dailyAt('06:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

/*
 * Sincronización de usuarios con la base institucional (RF19, RF20). Hoy lee
 * la fuente simulada (SINCRONIZACION_FUENTE=simulada) mientras no haya acceso
 * a la vista de la universidad. De madrugada, fuera del horario de clases, y
 * solo con SINCRONIZACION_PROGRAMADA=true: a mano se corre siempre.
 */
Artisan::command('usuarios:sincronizar', function (UsuarioSyncService $sincronizacion): int {
    $resultado = $sincronizacion->sincronizar();

    if ($resultado->detenida) {
        $this->warn($resultado->resumen());

        return 1;
    }

    $this->info($resultado->resumen());

    return 0;
})->purpose('Sincroniza las cuentas con la base institucional (RF20)');

/*
 * El usuario de PostgreSQL de la aplicación, sin superusuario (S.1). Lo
 * corre el servicio "migraciones" del compose de producción, con el dueño de
 * la base, en cada despliegue: la primera vez lo crea y después le renueva
 * la contraseña y los permisos.
 */
Artisan::command('base:usuario-de-la-aplicacion', function (UsuarioDeLaAplicacionService $usuarios): int {
    $usuario = (string) config('database.usuario_de_la_aplicacion.usuario');

    try {
        $usuarios->crearOActualizar($usuario, (string) config('database.usuario_de_la_aplicacion.contrasena'));
    } catch (UsuarioDeLaBaseInvalido $invalido) {
        $this->error($invalido->getMessage());

        return 1;
    }

    $this->info(sprintf('La aplicación entra a la base como «%s», sin superusuario: solo lee y escribe filas.', $usuario));

    return 0;
})->purpose('Crea o actualiza el usuario de PostgreSQL de la aplicación, sin superusuario (S.1)');

/*
 * El primer ADMIN de una instalación: la base nace sin cuentas y la entrada
 * con Google no crea ninguna (RF18), así que sin esto nadie podría entrar.
 * Sirve también si el último ADMIN se fue. Lo corre "produccion.sh instalar";
 * a mano, en el servidor:
 *   docker compose -f docker-compose.produccion.yml exec app php artisan usuarios:crear-administrador correo@dominio.edu.co "Nombre Apellido"
 */
Artisan::command(
    'usuarios:crear-administrador {email : Correo con el que entrará por Google} {nombre? : Nombre completo, si la cuenta no existe}',
    function (UsuarioService $usuarios, AccesoService $acceso): int {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $nombre = $this->argument('nombre');

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error(sprintf('«%s» no es un correo válido.', $email));

            return 1;
        }

        try {
            $nombrado = $usuarios->nombrarAdministradorDesdeLaConsola($email, is_string($nombre) ? $nombre : null);
        } catch (UsuarioInvalido $invalido) {
            $this->error($invalido->getMessage());

            return 1;
        } catch (RoleDoesNotExist) {
            $this->error('La base todavía no tiene los roles: primero corre las migraciones (docker compose -f docker-compose.produccion.yml run --rm migraciones).');

            return 1;
        }

        $this->info($nombrado->resumen());

        if (! $acceso->puedeEntrar($nombrado->cuenta)) {
            $this->warn($nombrado->cuenta->estaDeshabilitado()
                ? 'Ojo: la cuenta está deshabilitada y así no puede entrar. La vuelve a habilitar otro ADMIN en /panel/usuarios.'
                : 'Ojo: la vinculación institucional de la cuenta no está vigente y así no puede entrar. La actualiza la sincronización.');
        }

        $dominio = $acceso->dominioInstitucional();

        if ($dominio !== null && ! str_ends_with($email, '@'.$dominio)) {
            $this->warn(sprintf('Ojo: la entrada con Google solo admite cuentas de %s, y este correo es de otro dominio.', $dominio));
        }

        if (! $acceso->entradaConGoogleDisponible()) {
            $this->warn('La entrada con Google todavía no está configurada (GOOGLE_CLIENT_ID en el .env): la cuenta queda lista para cuando lo esté.');
        }

        return 0;
    },
)->purpose('Crea la cuenta del ADMIN, o le da el rol a una que ya existe, desde la consola del servidor');

Schedule::command('usuarios:sincronizar')
    ->cron((string) config('laboratorio.sincronizacion.frecuencia'))
    ->timezone(config('app.timezone'))
    ->when(static fn (): bool => (bool) config('laboratorio.sincronizacion.programada'))
    ->withoutOverlapping();
