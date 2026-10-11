<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\EstadoDelPaso;
use App\Enums\EstadoPreparacion;
use App\Enums\EstadoSolicitud;
use App\Enums\OrigenSolicitud;
use App\Models\Solicitud;

/**
 * Las fases de una solicitud, para la línea de estados del expediente.
 *
 * Solo traduce el estado que ya tiene la solicitud a lo que se dibuja: no
 * decide nada. Las transiciones las hace SolicitudService.
 *
 * - Del docente: solicitud, revisión, aprobación y montaje.
 * - Apartada antes del semestre (RF57): nace aprobada, así que empieza en el
 *   registro y no pasa por la revisión.
 * - Rechazada: la línea se corta en la fase donde se rechazó (la pendiente,
 *   en la revisión; la revisada, en la aprobación) y no sigue.
 *
 * La fase actual es la primera que falta. Cuando no falta ninguna, es la
 * última: el escenario está listo y ahí se queda.
 */
final class LineaDeEstados
{
    /** @return list<PasoDeLaLinea> */
    public static function deSolicitud(Solicitud $solicitud): array
    {
        if ($solicitud->estado === EstadoSolicitud::Rechazada) {
            return self::rechazada($solicitud);
        }

        $aprobada = $solicitud->estado === EstadoSolicitud::Aprobada;
        $preparacion = $solicitud->preparacion;

        /** @var list<array{fase: string, hecha: bool, fecha: mixed, situacion: string}> $fases */
        $fases = $solicitud->origen === OrigenSolicitud::RegistroPrevio
            ? [
                ['fase' => 'Registro', 'hecha' => true, 'fecha' => $solicitud->created_at, 'situacion' => 'Registrada'],
                ['fase' => 'Aprobación', 'hecha' => true, 'fecha' => $solicitud->resuelta_at ?? $solicitud->created_at, 'situacion' => 'Aprobada'],
            ]
            : [
                ['fase' => 'Solicitud', 'hecha' => true, 'fecha' => $solicitud->created_at, 'situacion' => 'Solicitada'],
                ['fase' => 'Revisión', 'hecha' => $solicitud->estado !== EstadoSolicitud::Pendiente, 'fecha' => $solicitud->revisada_at, 'situacion' => 'En revisión'],
                ['fase' => 'Aprobación', 'hecha' => $aprobada, 'fecha' => $solicitud->resuelta_at, 'situacion' => 'Por aprobar'],
            ];

        $lista = $preparacion?->estado === EstadoPreparacion::Preparado;
        $fases[] = [
            'fase' => 'Montaje',
            'hecha' => $lista,
            'fecha' => $preparacion?->preparado_at,
            'situacion' => match (true) {
                $lista => 'Escenario listo',
                $preparacion?->estado === EstadoPreparacion::EnPreparacion => 'Montando',
                default => 'Por montar',
            },
        ];

        return self::marcarLaActual($fases);
    }

    /** @return list<PasoDeLaLinea> */
    private static function rechazada(Solicitud $solicitud): array
    {
        $cuando = $solicitud->resuelta_at ?? $solicitud->updated_at;
        $pasos = [new PasoDeLaLinea('Solicitud', EstadoDelPaso::Hecho, $solicitud->created_at)];

        // Rechazada en la primera fase: nadie la revisó (CLAUDE.md, regla 9).
        if ($solicitud->revisada_por === null) {
            $pasos[] = new PasoDeLaLinea('Revisión', EstadoDelPaso::Cortado, $cuando, 'Rechazada');

            return $pasos;
        }

        $pasos[] = new PasoDeLaLinea('Revisión', EstadoDelPaso::Hecho, $solicitud->revisada_at);
        $pasos[] = new PasoDeLaLinea('Aprobación', EstadoDelPaso::Cortado, $cuando, 'Rechazada');

        return $pasos;
    }

    /**
     * @param  list<array{fase: string, hecha: bool, fecha: mixed, situacion: string}>  $fases
     * @return list<PasoDeLaLinea>
     */
    private static function marcarLaActual(array $fases): array
    {
        $actual = array_key_last($fases);
        foreach ($fases as $indice => $fase) {
            if (! $fase['hecha']) {
                $actual = $indice;
                break;
            }
        }

        $pasos = [];
        foreach ($fases as $indice => $fase) {
            $pasos[] = match (true) {
                $indice === $actual => new PasoDeLaLinea($fase['fase'], EstadoDelPaso::Actual, $fase['hecha'] ? $fase['fecha'] : null, $fase['situacion']),
                $fase['hecha'] => new PasoDeLaLinea($fase['fase'], EstadoDelPaso::Hecho, $fase['fecha']),
                default => new PasoDeLaLinea($fase['fase'], EstadoDelPaso::Pendiente),
            };
        }

        return $pasos;
    }

    /**
     * Lo que pasa ahora con la solicitud, en palabras: la situación de la
     * fase actual o del corte. La usan las filas de las listas.
     *
     * @param  list<PasoDeLaLinea>  $pasos
     */
    public static function situacion(array $pasos): string
    {
        foreach ($pasos as $paso) {
            if (in_array($paso->estado, [EstadoDelPaso::Actual, EstadoDelPaso::Cortado], true)) {
                return (string) $paso->situacion;
            }
        }

        return '';
    }
}
