<?php

declare(strict_types=1);

namespace App\Filament\Resources\EstadisticaLandingResource\Pages;

use App\Filament\Concerns\EntraAlFinalDelOrden;
use App\Filament\Resources\EstadisticaLandingResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateEstadisticaLanding extends CreateRecord
{
    use EntraAlFinalDelOrden;

    protected static string $resource = EstadisticaLandingResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
