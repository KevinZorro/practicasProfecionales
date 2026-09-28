<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerfilDocenteResource\Pages;

use App\Filament\Resources\PerfilDocenteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListPerfilesDocentes extends ListRecords
{
    protected static string $resource = PerfilDocenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
