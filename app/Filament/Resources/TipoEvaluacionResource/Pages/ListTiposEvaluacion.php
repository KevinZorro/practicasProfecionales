<?php

declare(strict_types=1);

namespace App\Filament\Resources\TipoEvaluacionResource\Pages;

use App\Filament\Resources\TipoEvaluacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

final class ListTiposEvaluacion extends ListRecords
{
    protected static string $resource = TipoEvaluacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
