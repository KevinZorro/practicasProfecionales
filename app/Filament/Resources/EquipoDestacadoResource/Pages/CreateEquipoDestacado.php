<?php

declare(strict_types=1);

namespace App\Filament\Resources\EquipoDestacadoResource\Pages;

use App\Filament\Concerns\EntraAlFinalDelOrden;
use App\Filament\Resources\EquipoDestacadoResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateEquipoDestacado extends CreateRecord
{
    use EntraAlFinalDelOrden;

    protected static string $resource = EquipoDestacadoResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
