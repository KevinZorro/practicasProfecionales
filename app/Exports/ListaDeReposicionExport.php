<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\Reporte;
use App\Models\LineaDeReposicion;

/**
 * RF67. Soporte de la carta de solicitud de compra.
 *
 * Lee las líneas congeladas de una lista cerrada, nunca el historial: lo que
 * se descarga tiene que ser lo mismo que se presentó, aunque el inventario
 * haya seguido moviéndose.
 */
final class ListaDeReposicionExport extends ReporteExport
{
    public function reporte(): Reporte
    {
        return Reporte::ListaDeReposicion;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ['Insumo o equipo', 'Motivo', 'Cantidad', 'En el catálogo'];
    }

    /**
     * @param  LineaDeReposicion  $fila
     * @return list<string|int>
     */
    public function map($fila): array
    {
        return [
            $fila->descripcion,
            $fila->motivo->etiqueta(),
            $fila->cantidad,
            $fila->item_inventario_id === null ? 'No' : 'Sí',
        ];
    }
}
