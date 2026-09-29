<?php

declare(strict_types=1);

namespace App\Filament\Resources\EstadisticaLandingResource\Pages;

use App\Filament\Resources\EstadisticaLandingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListEstadisticasLanding extends ListRecords
{
    protected static string $resource = EstadisticaLandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
