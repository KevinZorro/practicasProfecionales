<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Los cinco roles de la plataforma. Se registran como roles de
 * spatie/laravel-permission; este enum evita escribir sus nombres a mano
 * en seeders, Policies y Gates.
 */
enum Rol: string
{
    case Admin = 'admin';
    case Coordinador = 'coordinador';
    case Administrativo = 'administrativo';
    case Docente = 'docente';
    case Estudiante = 'estudiante';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador de la plataforma',
            self::Coordinador => 'Coordinador',
            self::Administrativo => 'Administrativo',
            self::Docente => 'Docente',
            self::Estudiante => 'Estudiante',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::Admin => 'Administra la plataforma: contenido público, usuarios, materias, casos clínicos, tipos de evaluación y el nivel de fidelidad del inventario.',
            self::Coordinador => 'Aprueba o rechaza solicitudes de escenario, verifica consentimientos y genera reportes. Hereda todos los permisos del administrativo.',
            self::Administrativo => 'Revisa solicitudes, asigna sala, prepara escenarios y gestiona el inventario.',
            self::Docente => 'Solicita escenarios y registra evaluaciones de habilidades.',
            self::Estudiante => 'Consulta sus resultados y entrega el consentimiento informado.',
        };
    }
}
