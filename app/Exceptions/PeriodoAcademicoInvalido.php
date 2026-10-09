<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\PeriodoAcademico;
use DomainException;

final class PeriodoAcademicoInvalido extends DomainException
{
    public static function yaHayUnoAbierto(PeriodoAcademico $abierto): self
    {
        return new self(sprintf('El periodo %s sigue abierto: ciérrelo antes de abrir otro.', $abierto->nombre));
    }

    public static function nombreRepetido(string $nombre): self
    {
        return new self(sprintf('Ya existe un periodo llamado %s.', $nombre));
    }

    public static function yaEstaCerrado(PeriodoAcademico $periodo): self
    {
        return new self(sprintf('El periodo %s ya está cerrado.', $periodo->nombre));
    }

    public static function soloSeReabreElUltimo(PeriodoAcademico $periodo): self
    {
        return new self(sprintf('Solo se puede reabrir el último periodo cerrado; %s no lo es.', $periodo->nombre));
    }
}
