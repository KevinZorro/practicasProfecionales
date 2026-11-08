<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Espera entre los reintentos de un trabajo de la cola (B3.2): un minuto
 * antes del segundo intento y cinco antes del tercero. Sin ella, los tres
 * intentos de queue:work van seguidos y un corte de Gmail de un par de
 * minutos los gasta todos; con ella, un correo aguanta un corte de unos diez
 * minutos antes de quedar en failed_jobs.
 *
 * La usan los listeners que van a la cola y los correos: Laravel la lee de
 * la propiedad $backoff del listener o del Mailable encolado.
 */
trait EsperaEntreReintentos
{
    /** @var list<int> segundos antes del segundo y del tercer intento */
    public array $backoff = [60, 300];
}
