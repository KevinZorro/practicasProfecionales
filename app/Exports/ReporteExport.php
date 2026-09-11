<?php

declare(strict_types=1);

namespace App\Exports;

use App\Enums\Reporte;
use App\Services\ReporteService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Base de las tres exportaciones a Excel.
 *
 * No construye ninguna consulta: recibe la del ReporteService y la recorre.
 * Cada hija solo decide encabezados y cómo se pinta una fila, y ese mismo
 * mapeo es el que usa el PDF (GeneradorDeReportes::filas), de modo que las
 * tres salidas no pueden divergir aunque se quiera.
 *
 * WithChunkReading hace que la hoja se arme por lotes en vez de cargar todo
 * el resultado en memoria (RNF10).
 */
abstract class ReporteExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithHeadings, WithMapping
{
    /** @param Builder<covariant \Illuminate\Database\Eloquent\Model> $consulta */
    public function __construct(protected Builder $consulta) {}

    abstract public function reporte(): Reporte;

    /** @return Builder<covariant \Illuminate\Database\Eloquent\Model> */
    public function query(): Builder
    {
        return $this->consulta;
    }

    public function chunkSize(): int
    {
        return ReporteService::TAMANO_DE_LOTE;
    }
}
