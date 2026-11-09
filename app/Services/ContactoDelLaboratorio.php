<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Cómo se le escribe o se le llama al laboratorio (RF11). Lo carga el
 * administrador de la plataforma con la portada, y lo repiten el pie del
 * panel, la entrada, el 403 y la firma de los correos: donde un mensaje
 * dice «comunícate con el laboratorio», tiene que decir cómo.
 */
final readonly class ContactoDelLaboratorio
{
    public function __construct(
        public ?string $correo,
        public ?string $telefono,
    ) {}

    public function hayContacto(): bool
    {
        return $this->correo !== null || $this->telefono !== null;
    }

    /** El teléfono como lo marca el celular: solo dígitos y el «+». */
    public function telefonoParaMarcar(): ?string
    {
        return $this->telefono === null ? null : preg_replace('/[^\d+]/', '', $this->telefono);
    }
}
