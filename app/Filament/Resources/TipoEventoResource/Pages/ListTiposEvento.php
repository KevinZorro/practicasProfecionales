<?php

declare(strict_types=1);

namespace App\Filament\Resources\TipoEventoResource\Pages;

use App\Filament\Resources\TipoEventoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListTiposEvento extends ListRecords
{
    protected static string $resource = TipoEventoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
