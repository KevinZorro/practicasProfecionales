<?php

declare(strict_types=1);

namespace App\Filament\Resources\EquipoDestacadoResource\Pages;

use App\Filament\Resources\EquipoDestacadoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListEquiposDestacados extends ListRecords
{
    protected static string $resource = EquipoDestacadoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
