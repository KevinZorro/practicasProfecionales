<?php

use App\Exceptions\UsuarioDeLaBaseInvalido;
use App\Services\RegistroPrevioService;
use App\Services\UsuarioDeLaAplicacionService;
use App\Services\UsuarioSyncService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

Schedule::command('usuarios:sincronizar')
    ->cron((string) config('laboratorio.sincronizacion.frecuencia'))
    ->timezone(config('app.timezone'))
    ->when(static fn (): bool => (bool) config('laboratorio.sincronizacion.programada'))
    ->withoutOverlapping();
