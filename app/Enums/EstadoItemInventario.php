<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado funcional de un ítem del inventario (RF66): si la pieza cumple su
 * función. No es disponibilidad.
 *
 * Disponibilidad responde "¿está libre para esta sesión?" y no se almacena:
 * InventarioService la calcula por franja horaria restando lo comprometido
 * en solicitudes aprobadas. Este enum responde "¿sirve?". Un ítem operativo
 * puede estar ocupado, y uno defectuoso no cuenta como disponible aunque
 * esté en su mueble.
 *
 * Los valores vienen del flujo real que describió el cliente: se detecta el
 * problema, el ítem pasa a revisión, se confirma como defectuoso y, si no
 * tiene arreglo, se da de baja. No hay fecha de vencimiento: son
 * simuladores, no insumos con caducidad.
 */
enum EstadoItemInventario: string
{
    case Operativo = 'operativo';
    case EnRevision = 'en_revision';
    case Defectuoso = 'defectuoso';
    case DadoDeBaja = 'dado_de_baja';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Operativo => 'Operativo',
            self::EnRevision => 'En revisión',
            self::Defectuoso => 'Defectuoso',
            self::DadoDeBaja => 'Dado de baja',
        };
    }

    /** Solo un ítem operativo puede comprometerse en una sesión (RF66). */
    public function permiteUso(): bool
    {
        return $this === self::Operativo;
    }

    /** La baja es definitiva: de ahí no se vuelve. */
    public function esDefinitivo(): bool
    {
        return $this === self::DadoDeBaja;
    }

    /**
     * A qué estados puede pasar este.
     *
     * Sigue el flujo del cliente y por eso no hay atajos: de operativo no se
     * salta a defectuoso sin pasar por revisión, porque el defecto se
     * confirma revisando. Volver a operativo sí es posible desde revisión
     * (falsa alarma) y desde defectuoso (se reparó).
     *
     * @return list<self>
     */
    public function siguientes(): array
    {
        return match ($this) {
            self::Operativo => [self::EnRevision],
            self::EnRevision => [self::Operativo, self::Defectuoso],
            self::Defectuoso => [self::Operativo, self::DadoDeBaja],
            self::DadoDeBaja => [],
        };
    }

    public function permitePasarA(self $destino): bool
    {
        return in_array($destino, $this->siguientes(), true);
    }
}
