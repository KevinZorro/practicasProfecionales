<?php

declare(strict_types=1);

namespace App\Filament\Resources\GaleriaFotoResource\Pages;

use App\Filament\Resources\GaleriaFotoResource;
use App\Models\GaleriaFoto;
use Filament\Resources\Pages\CreateRecord;

final class CreateGaleriaFoto extends CreateRecord
{
    protected static string $resource = GaleriaFotoResource::class;

    /**
     * Una foto nueva entra al final de la galería; después se reordena
     * arrastrando las filas del listado.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['orden'] = (int) GaleriaFoto::query()->max('orden') + 1;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
