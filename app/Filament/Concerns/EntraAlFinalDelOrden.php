<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

/**
 * Para las páginas de alta del contenido público: lo nuevo entra al final
 * de la columna "orden", que es el orden en que sale en la landing. Después
 * se reordena arrastrando las filas del listado.
 */
trait EntraAlFinalDelOrden
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['orden'] = (int) static::getModel()::query()->max('orden') + 1;

        return $data;
    }
}
