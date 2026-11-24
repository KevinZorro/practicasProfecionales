<?php

use App\Exceptions\UsuarioDeLaBaseInvalido;
use App\Exceptions\UsuarioInvalido;
use App\Models\User;
use App\Services\AccesoService;
use App\Services\CuentaDeDemostracion;
use App\Services\DemostracionService;
use App\Services\RegistroPrevioService;
use App\Services\UsuarioDeLaAplicacionService;
use App\Services\UsuarioService;
use App\Services\UsuarioSyncService;
use App\Support\MuestrasDeCorreo;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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

        if ($acceso->entradaConContrasenaDisponible() && $nombrado->cuenta->password === null) {
            $this->line(sprintf('Para entrar con contraseña: php artisan usuarios:contrasena %s', $email));
        }

        return 0;
    },
)->purpose('Crea la cuenta del ADMIN, o le da el rol a una que ya existe, desde la consola del servidor');

/*
 * La contraseña de una cuenta, para entrar con correo y contraseña
 * (ADR 0011). Mientras no haya recuperación por correo, la fija o la
 * cambia quien tiene la consola del servidor:
 *   php artisan usuarios:contrasena correo@dominio.edu.co             (la pregunta, sin mostrarla)
 *   php artisan usuarios:contrasena correo@dominio.edu.co --generar   (inventa una y la muestra una vez)
 */
Artisan::command(
    'usuarios:contrasena {email : Correo de la cuenta} {--generar : Inventa una contraseña segura y la muestra una sola vez}',
    function (UsuarioService $usuarios, AccesoService $acceso): int {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $generada = (bool) $this->option('generar');

        if ($generada) {
            $contrasena = Str::password(16, symbols: false);
        } else {
            $contrasena = (string) $this->secret('Contraseña nueva');

            if ($contrasena !== (string) $this->secret('Repítela')) {
                $this->error('Las dos contraseñas no coinciden. No se cambió nada.');

                return 1;
            }
        }

        try {
            $cuenta = $usuarios->fijarContrasenaDesdeLaConsola($email, $contrasena);
        } catch (UsuarioInvalido $invalido) {
            $this->error($invalido->getMessage());

            return 1;
        }

        $this->info(sprintf('Quedó la contraseña de %s (%s).', $cuenta->nombre, $cuenta->email));

        if ($generada) {
            $this->line('Contraseña: '.$contrasena);
            $this->line('Entrégasela a la persona por un medio privado: no se vuelve a mostrar.');
        }

        if (! $acceso->entradaConContrasenaDisponible()) {
            $this->warn('Ojo: la entrada con contraseña está apagada (ENTRADA_CON_CONTRASENA=false en el .env).');
        }

        if (! $acceso->puedeEntrar($cuenta)) {
            $this->warn($acceso->motivoDelRechazo($cuenta));
        }

        return 0;
    },
)->purpose('Fija o cambia la contraseña de una cuenta para entrar con correo y contraseña');

Schedule::command('usuarios:sincronizar')
    ->cron((string) config('laboratorio.sincronizacion.frecuencia'))
    ->timezone(config('app.timezone'))
    ->when(static fn (): bool => (bool) config('laboratorio.sincronizacion.programada'))
    ->withoutOverlapping();

/*
 * Una muestra de cada correo de la aplicación, para ver en un buzón real
 * cómo llegan sin montar solicitudes, salas ni docentes de prueba. Los
 * datos son inventados y no se guarda nada en la base. Sale en el acto,
 * sin pasar por la cola: sirve también para probar el SMTP sin el cron.
 *   php artisan correos:muestra correo@dominio.edu.co
 */
Artisan::command(
    'correos:muestra {email : A quién le llegan las muestras}',
    function (MuestrasDeCorreo $muestras): int {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Ese correo no es válido.');

            return 1;
        }

        $nombre = User::query()->whereRaw('LOWER(email) = ?', [$email])->value('nombre') ?? 'Persona de prueba';
        $fallidos = 0;

        foreach ($muestras->para($nombre) as $clase => $correo) {
            try {
                Mail::to($email)->send($correo);
                $this->line('Enviado: '.$clase);
            } catch (Throwable $error) {
                $this->error(sprintf('No salió %s: %s', $clase, $error->getMessage()));
                $fallidos++;
            }
        }

        if ($fallidos > 0) {
            $this->warn('Revisa las variables MAIL_* del .env.');

            return 1;
        }

        $this->info(sprintf('Listo: revisa la bandeja de %s.', $email));

        return 0;
    },
)->purpose('Envía a un correo una muestra de cada correo de la aplicación, con datos inventados');

/*
 * Datos de demostración para probar a mano el flujo de una solicitud y cada
 * correo que dispara, sin montarlo todo desde la pantalla. Crea cuatro
 * cuentas que son alias del correo dado (tu+docente@…), dos materias, cuatro
 * casos clínicos, dos salas y una solicitud por cada camino del flujo, e
 * imprime qué hacer con cada cuenta. Repetirlo lo deja como recién hecho, con
 * contraseñas nuevas. --limpiar borra solo lo de la demostración.
 *   php artisan demo:preparar tu@correo.com
 *   php artisan demo:preparar --limpiar
 * En producción (el sandbox lo es) exige --en-produccion: ahí conviven con
 * datos reales.
 */
Artisan::command(
    'demo:preparar {email? : Tu correo: las cuentas de la demostración son alias suyos}
        {--limpiar : Borra solo los datos de demostración}
        {--en-produccion : Permite correrlo con APP_ENV=production}',
    function (DemostracionService $demo): int {
        if (app()->isProduction() && ! $this->option('en-produccion')) {
            $this->error('Esta instalación es de producción. Si es el sandbox y quieres los datos de demostración, repite con --en-produccion.');

            return 1;
        }

        if ($this->option('limpiar')) {
            $limpia = $demo->limpiar();
            $this->info(sprintf('Borradas: %d solicitudes y %d cuentas de demostración, con sus materias, casos clínicos y salas.', $limpia->solicitudes, $limpia->cuentasBorradas));

            foreach ($limpia->cuentasDeshabilitadas as $email) {
                $this->warn(sprintf('%s actuó sobre datos reales: no se borró, quedó deshabilitada.', $email));
            }

            foreach ($limpia->salasDesactivadas as $codigo) {
                $this->warn(sprintf('La sala %s la usa una sesión real: no se borró, quedó inactiva.', $codigo));
            }

            return 0;
        }

        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            $this->error('Falta tu correo, o no es válido: php artisan demo:preparar tu@correo.com');

            return 1;
        }

        $hecha = $demo->preparar($email);
        $c = $hecha->cuentas;
        $s = $hecha->solicitudes;
        $de = static fn (string $clave): string => sprintf(
            '«%s» del %s',
            Str::after($s[$clave]->casoClinico->nombre, DemostracionService::ETIQUETA.' '),
            $s[$clave]->fecha->format('d/m/Y'),
        );

        $this->info('Datos de demostración listos. Las contraseñas no se vuelven a mostrar.');
        $this->table(
            ['Rol', 'Correo', 'Contraseña'],
            array_map(static fn (CuentaDeDemostracion $cuenta): array => [$cuenta->rol->etiqueta(), $cuenta->email, $cuenta->contrasena], array_values($c)),
        );

        $this->line(sprintf('Entra en %s con cada cuenta. Las ventanas privadas de un navegador comparten la sesión: para tener', route('acceso')));
        $this->line('varias cuentas abiertas a la vez usa navegadores o perfiles distintos, o una cuenta a la vez (si no, error 419).');
        $this->line('Los correos salen por la cola: sin el cron no llegan.');
        $this->newLine();
        $this->line('1. Aprobar y asignar sala, '.$de('para_aprobar').':');
        $this->line('   Administrativo la acepta. Coordinación la aprueba → al docente: solicitud aprobada.');
        $this->line('   Administrativo le asigna sala en Preparación → al docente: sala asignada.');
        $this->line('2. Rechazar, '.$de('para_rechazar').':');
        $this->line('   Administrativo la rechaza con un motivo → al docente: solicitud rechazada.');
        $this->line('3. Ya revisada, '.$de('revisada').':');
        $this->line('   Coordinación la aprueba o la rechaza → al docente: el correo que toque.');
        $this->line('4. Ya aprobada, sin sala, '.$de('aprobada').':');
        $this->line('   Administrativo le asigna sala → al docente: sala asignada.');
        $this->line('   La reprograma (otra fecha o «Atención de parto») → al docente: sesión reprogramada.');
        $this->line('   Cambia el docente por «'.$c['docente2']->nombre.'» → al reemplazo: sustitución de docente.');
        $this->line('5. Sin formato intramural, '.$de('sin_formato_intramural').':');
        $this->line('   Sale solo a las 6:00, o ya con: php artisan sesiones:avisar-formato-intramural');
        $this->line('   → al docente y a cada administrativo activo: aviso de formato intramural.');
        $this->line('6. Contraseña: «¿Olvidaste tu contraseña?» con cualquier cuenta → enlace para elegirla;');
        $this->line('   al cambiarla → aviso de contraseña cambiada.');
        $this->newLine();
        $this->line('Para borrarlo todo: php artisan demo:preparar --limpiar'.($this->option('en-produccion') ? ' --en-produccion' : ''));

        return 0;
    },
)->purpose('Crea cuentas y solicitudes de demostración para probar el flujo y sus correos');
