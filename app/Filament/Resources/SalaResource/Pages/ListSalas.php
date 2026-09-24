<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalaResource\Pages;

use App\Filament\Resources\SalaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListSalas extends ListRecords
{
    protected static string $resource = SalaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
