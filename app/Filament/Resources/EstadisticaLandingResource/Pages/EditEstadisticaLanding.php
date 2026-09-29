<?php

declare(strict_types=1);

namespace App\Filament\Resources\EstadisticaLandingResource\Pages;

use App\Filament\Resources\EstadisticaLandingResource;
use Filament\Resources\Pages\EditRecord;

final class EditEstadisticaLanding extends EditRecord
{
    protected static string $resource = EstadisticaLandingResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
