<?php

use App\Services\RegistroPrevioService;
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
