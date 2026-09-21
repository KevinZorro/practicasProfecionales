<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\EstadoItemInventario;
use App\Enums\TipoItemInventario;
use DomainException;

final class InventarioInvalido extends DomainException
{
    public static function nivelDeFidelidadReservadoAlAdmin(): self
    {
        return new self('El nivel de fidelidad de un simulador solo lo registra el ADMIN.');
    }

    public static function transicionDeEstadoInvalida(EstadoItemInventario $desde, EstadoItemInventario $hasta): self
    {
        return new self(sprintf(
            'Un ítem "%s" no puede pasar a "%s": el flujo es operativo → en revisión → defectuoso → dado de baja.',
            $desde->etiqueta(),
            $hasta->etiqueta(),
        ));
    }

    public static function elMotivoEsObligatorio(): self
    {
        // Sin motivo el historial no sirve para nada: el RF66 pide saber por
        // qué cambió cada estado, no solo que cambió.
        return new self('Todo cambio de estado funcional necesita un motivo.');
    }

    public static function laBajaEsDefinitiva(): self
    {
        return new self('Un ítem dado de baja no vuelve a cambiar de estado.');
    }

    public static function nivelDeFidelidadSoloEnSimuladores(TipoItemInventario $tipo): self
    {
        return new self(sprintf(
            'Un ítem de tipo "%s" no lleva nivel de fidelidad: solo los simuladores lo tienen.',
            $tipo->value,
        ));
    }
}
