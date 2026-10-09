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
    public static function noSonEstudiantesActivos(array $nombres): self
    {
        return new self(sprintf(
            'Solo se pueden incluir estudiantes con cuenta activa. No lo son: %s.',
            implode(', ', $nombres),
        ));
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

    public static function grupoInvalido(string $grupo): self
    {
        return new self(sprintf('El grupo se identifica con una o dos letras (A, B, C…); se recibió "%s".', $grupo));
    }
}
