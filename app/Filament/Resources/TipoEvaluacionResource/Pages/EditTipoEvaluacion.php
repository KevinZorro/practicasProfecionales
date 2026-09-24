<?php

declare(strict_types=1);

namespace App\Filament\Resources\TipoEvaluacionResource\Pages;

use App\Filament\Resources\TipoEvaluacionResource;
use Filament\Resources\Pages\EditRecord;

/** Sin acción de borrar en la cabecera: los tipos de evaluación se desactivan. */
final class EditTipoEvaluacion extends EditRecord
{
    protected static string $resource = TipoEvaluacionResource::class;

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }
}
