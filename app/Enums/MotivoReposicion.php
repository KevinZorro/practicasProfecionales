<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Por qué una línea entra en la lista de reposición (RF67).
 *
 * Las dos primeras salen solas del historial de inventario: desde RF66.2,
 * una salida de unidades lleva el estado del que venían, y eso ya distingue
 * lo que se gastó de lo que se averió. La tercera es lo que el laboratorio
 * anotaba a mano en una hoja aparte: lo que se pidió y no había.
 */
enum MotivoReposicion: string
{
    case Consumo = 'consumo';
    case Averia = 'averia';
    case Solicitada = 'solicitada';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Consumo => 'Consumo',
            self::Averia => 'Avería',
            self::Solicitada => 'Solicitada y no disponible',
        };
    }

    /**
     * El motivo de una salida de unidades, según de qué estado venían.
     *
     * El flujo del RF66 solo deja llegar a la baja desde "operativo" —gasto,
     * pérdida o corrección de conteo— y desde "defectuoso" —avería
     * confirmada—. Cualquier otro origen sería una avería por definición: si
     * la unidad no estaba operativa, algo le pasaba.
     */
    public static function deUnaSalida(?EstadoItemInventario $origen): self
    {
        return $origen === EstadoItemInventario::Operativo ? self::Consumo : self::Averia;
    }
}
