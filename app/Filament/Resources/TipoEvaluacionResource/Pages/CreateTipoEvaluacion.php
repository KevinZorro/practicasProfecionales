<?php

declare(strict_types=1);

namespace App\Filament\Resources\TipoEvaluacionResource\Pages;

use App\Filament\Resources\TipoEvaluacionResource;
use Filament\Resources\Pages\CreateRecord;

final class CreateTipoEvaluacion extends CreateRecord
{
    protected static string $resource = TipoEvaluacionResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
