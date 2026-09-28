<?php

declare(strict_types=1);

namespace App\Filament\Resources\GaleriaFotoResource\Pages;

use App\Filament\Resources\GaleriaFotoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListGaleriaFotos extends ListRecords
{
    protected static string $resource = GaleriaFotoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
