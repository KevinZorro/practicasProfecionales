<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrigenUsuario;
use RuntimeException;

/**
 * Fuente institucional de mentira: lee un archivo JSON con el formato de
 * PersonaInstitucional (database/datos/institucional-simulada.json). Sirve
 * para desarrollar y probar la sincronización mientras no hay acceso a la
 * base de la universidad (RF20).
 */
final class FuenteInstitucionalSimulada implements FuenteInstitucional
{
    public function __construct(private readonly string $archivo) {}

    public function personas(): array
    {
        $contenido = is_file($this->archivo) ? file_get_contents($this->archivo) : false;

        if ($contenido === false) {
            throw new RuntimeException(sprintf('No se encontró el archivo de la fuente simulada: %s', $this->archivo));
        }

        /** @var list<array{documento: string, codigo?: string|null, nombre: string, email: string, programa: string, vinculacion: string, vigente: bool}> $filas */
        $filas = json_decode($contenido, true, flags: JSON_THROW_ON_ERROR);

        return array_map(static fn (array $fila): PersonaInstitucional => new PersonaInstitucional(
            documento: (string) $fila['documento'],
            codigo: $fila['codigo'] ?? null,
            nombre: $fila['nombre'],
            email: $fila['email'],
            programa: $fila['programa'],
            vinculacion: OrigenUsuario::from($fila['vinculacion']),
            vigente: (bool) $fila['vigente'],
        ), $filas);
    }
}
