<?php

declare(strict_types=1);

use App\Enums\ModalidadTaller;
use App\Enums\Rol;
use App\Enums\TipoSesion;

it('define la modalidad de un taller como virtual o presencial', function (): void {
    // El RF04 fija exactamente estas dos.
    expect(array_column(ModalidadTaller::cases(), 'value'))
        ->toEqualCanonicalizing(['virtual', 'presencial']);
});

it('define los cinco roles de la plataforma', function (): void {
    expect(array_column(Rol::cases(), 'value'))
        ->toEqualCanonicalizing(['admin', 'coordinador', 'administrativo', 'docente', 'estudiante']);
});

it('define la sesión como práctica o evaluación', function (): void {
    expect(array_column(TipoSesion::cases(), 'value'))
        ->toEqualCanonicalizing(['practica', 'evaluacion']);
});
