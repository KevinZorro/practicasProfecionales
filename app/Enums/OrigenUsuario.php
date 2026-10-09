<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * De dónde viene una cuenta. Las dos primeras las trae la sincronización
 * institucional (RF20), según la vinculación; la manual la crea el ADMIN
 * (RF22), y la sincronización no la toca (D6).
 */
enum OrigenUsuario: string
{
    case Matriculado = 'matriculado';
    case Contratado = 'contratado';
    case Manual = 'manual';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Matriculado => 'Matriculado',
            self::Contratado => 'Contratado',
            self::Manual => 'Creado en la plataforma',
        };
    }

    public function vieneDeLaSincronizacion(): bool
    {
        return $this !== self::Manual;
    }

    /**
     * El rol permanente que corresponde a la vinculación institucional. Los
     * contratados de la vista son docentes; el personal administrativo y los
     * pasantes reciben su rol a mano.
     */
    public function rolPermanente(): ?Rol
    {
        return match ($this) {
            self::Matriculado => Rol::Estudiante,
            self::Contratado => Rol::Docente,
            self::Manual => null,
        };
    }
}
