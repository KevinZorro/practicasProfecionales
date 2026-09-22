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

    /**
     * Roles que firman el formato de confidencialidad: todo el que entra a
     * la práctica, docentes incluidos (RF51-RF52).
     *
     * Vive aquí y no repartido porque lo consultan la Policy —que decide
     * quién puede entregarlo— y el Service —que arma la lista de quién lo
     * debe—. Si mañana entra otro rol al laboratorio, cambia en un sitio.
     *
     * @return list<string>
     */
    public static function queFirmanElFormato(): array
    {
        return [self::Estudiante->value, self::Docente->value];
    }

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
            self::Coordinador => 'Aprueba o rechaza solicitudes de escenario, verifica formatos y genera reportes. Hereda todos los permisos del administrativo.',
            self::Administrativo => 'Revisa solicitudes, asigna sala, prepara escenarios y gestiona el inventario.',
            self::Docente => 'Solicita escenarios, registra evaluaciones de habilidades y entrega el formato de confidencialidad.',
            self::Estudiante => 'Consulta sus resultados y entrega el formato de confidencialidad.',
        };
    }
}
