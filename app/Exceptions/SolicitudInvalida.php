<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

final class SolicitudInvalida extends DomainException
{
    public static function sinEstudiantes(): self
    {
        return new self('Indique qué estudiantes van a la sesión.');
    }

    /**
     * @param  list<string>  $nombres
     */
    /**
     * @param  list<string>  $inactivos  estudiantes que ya no tienen cuenta activa
     * @param  int  $ajenos  cuántos de los ids ni siquiera son estudiantes
     */
    public static function noSonEstudiantesActivos(array $inactivos, int $ajenos = 0): self
    {
        // Los nombres solo de quienes son estudiantes: un id de otra persona
        // (un docente, coordinación) no debe revelar quién es (QA-19).
        $partes = [];

        if ($inactivos !== []) {
            $partes[] = 'sin cuenta activa: '.implode(', ', $inactivos);
        }

        if ($ajenos > 0) {
            $partes[] = $ajenos === 1 ? '1 persona que no es estudiante' : $ajenos.' personas que no son estudiantes';
        }

        return new self('Solo se pueden incluir estudiantes con cuenta activa. No cumplen: '.implode('; ', $partes).'.');
    }

    public static function docenteBloqueado(string $motivo): self
    {
        return new self(sprintf('No puede solicitar escenarios mientras tenga un bloqueo vigente. Motivo: %s', $motivo));
    }

    public static function sesionCerrada(): self
    {
        return new self('La lista de estudiantes ya no se puede cambiar: la sesión pasó o fue rechazada.');
    }

    public static function noEstaEnLaSesion(string $nombre): self
    {
        return new self(sprintf('%s no está en la lista de esta sesión.', $nombre));
    }

    public static function yaFueRetirado(string $nombre): self
    {
        return new self(sprintf('%s fue retirado de esta sesión; el retiro queda registrado y no se deshace.', $nombre));
    }

    public static function sinMotivoDeRetiro(): self
    {
        return new self('Escriba el motivo del retiro.');
    }

    public static function formatoIntramuralVacio(): self
    {
        return new self('El formato intramural necesita al menos un insumo, equipo o simulador.');
    }

    public static function noEsDocente(): self
    {
        return new self('La sesión tiene que quedar a nombre de un docente.');
    }

    public static function casoFueraDeLaMateria(): self
    {
        return new self('Ese escenario clínico no está asociado a la materia elegida.');
    }

    public static function equipoInvalido(): self
    {
        return new self('Alguno de los equipos pedidos no está en el inventario o trae una cantidad que no es válida.');
    }

    public static function fechaPasada(): self
    {
        return new self('Ese día ya pasó: la sesión tiene que ser de hoy en adelante.');
    }

    public static function grupoInvalido(string $grupo): self
    {
        return new self(sprintf('El grupo se identifica con una o dos letras (A, B, C…); se recibió "%s".', $grupo));
    }
}
