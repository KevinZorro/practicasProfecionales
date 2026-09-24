<?php

declare(strict_types=1);

namespace App\Filament\Resources\CasoClinicoResource\Pages;

use App\Filament\Resources\CasoClinicoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListCasosClinicos extends ListRecords
{
    protected static string $resource = CasoClinicoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
