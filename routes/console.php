<?php

use App\Services\RegistroPrevioService;
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

Schedule::command('usuarios:sincronizar')
    ->cron((string) config('laboratorio.sincronizacion.frecuencia'))
    ->timezone(config('app.timezone'))
    ->when(static fn (): bool => (bool) config('laboratorio.sincronizacion.programada'))
    ->withoutOverlapping();
